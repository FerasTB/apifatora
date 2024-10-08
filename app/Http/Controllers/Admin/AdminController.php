<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Bill;
use App\Models\Fee;
use App\Models\Invoice;
use App\Models\PaymentSystemAccount;
use App\Models\Refund;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    /**
     * Middleware to ensure only admins can access these methods.
     */
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'checkRole:admin']);
    }

    /**
     * Add a new user or third-party app.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'nullable|email|unique:users,email',
            'ids'    => 'nullable|string|unique:users,ids',
            'phone'    => 'required|string|unique:users,phone',
            'password' => 'required|string|min:6',
            // 'role'     => 'required|in:user,third_party_app',
            // Additional fields if necessary
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'phone'    => $request->phone,
            'ids'    => $request->ids,
            'password' => Hash::make($request->password),
            // 'role'     => $request->role,
            // Set other fields as needed
        ]);

        return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
    }

    /**
     * View all users or third-party apps.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listUsers(Request $request)
    {
        $role = $request->query('role'); // Optional filter by role

        $query = User::query();

        if ($role && in_array($role, ['user', 'third_party_app'])) {
            $query->where('role', $role);
        }

        $users = $query->get(); // Adjust pagination as needed

        return response()->json($users);
    }

    public function listUser(Request $request, User $user)
    {
        return response()->json($user);
    }

    /**
     * Add a bill to a user.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addBill(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id'   => 'required|exists:users,id',
            'bill_type' => 'required|string|max:255',
            'bill_info' => 'required|string|max:255',
            'amount'    => 'nullable|numeric',
            'due_date'  => 'nullable|date|after_or_equal:today',
            // Additional fields if necessary
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bill = Bill::create([
            'user_id'   => $request->user_id,
            'bill_type' => $request->bill_type,
            'bill_info' => $request->bill_info,
            'amount'    => $request->amount,
            'due_date'  => $request->due_date,
            'status'    => 'active',
        ]);

        return response()->json(['message' => 'Bill added successfully', 'bill' => $bill], 201);
    }

    public function editBill(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'bill_info' => 'nullable|string|max:255',
            'status'    => 'nullable|in:active,notActive,missingInfo',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bill = Bill::find($id);

        if (!$bill) {
            return response()->json(['message' => 'Bill not found'], 404);
        }

        // Update fields only if provided
        if ($request->has('bill_info')) {
            $bill->bill_info = $request->bill_info;
        }
        if ($request->has('status')) {
            $bill->status = $request->status;
        }

        $bill->save();

        return response()->json(['message' => 'Bill updated successfully', 'bill' => $bill], 200);
    }

    public function deleteBill(Bill $bill)
    {

        $bill->delete();

        return response()->json(['message' => 'Bill deleted successfully'], 200);
    }

    /**
     * Add an invoice to a user.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addInvoice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id'    => 'required|exists:users,id',
            'amount'     => 'required|numeric|min:0.01',
            'bill_id'    => 'nullable|exists:bills,id',
            'description' => 'nullable|string',
            // Additional fields if necessary
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $invoice = Invoice::create([
            'user_id'     => $request->user_id,
            'bill_id'     => $request->bill_id,
            'amount'      => $request->amount,
            'description' => $request->description,
            'status'      => 'paid',
        ]);

        return response()->json(['message' => 'Invoice created successfully', 'invoice' => $invoice], 201);
    }

    public function createInvoiceForBill(Request $request, User $user, Bill $bill)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'amount'      => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ], [
            'amount.required' => 'المبلغ مطلوب',
            'amount.numeric'  => 'المبلغ يجب أن يكون رقمًا',
            'amount.min'      => 'المبلغ يجب أن يكون أكبر من صفر',
            'description.max' => 'الوصف لا يجب أن يتجاوز 255 حرفًا',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        abort_unless($user->balance >= $request->amount, 401, "المبلغ المطلوب غير متوفر");
        // Begin database transaction
        DB::beginTransaction();

        try {
            // Create invoice for the user and bill
            $invoice = Invoice::create([
                'user_id'     => $user->id,
                'bill_id'     => $bill->id ?? null,
                'amount'      => $request->amount,
                'status'      => 'paid', // Default unpaid
                'description' => $request->description ?? 'فاتورة للخدمة رقم ' . $bill->id,
            ]);

            // Calculate the fee (2% of the invoice amount)
            $feeAmount = $invoice->amount * 0.02;

            // Split fee into 20% and 80%
            $systemFeesAccountAmount = $feeAmount * 0.20;
            $fatorahAccountAmount = ($feeAmount * 0.80) + integerValue($invoice->amount - $feeAmount);

            // Create the transaction for payments
            $transaction = Transaction::create([
                'user_id'       => $user->id,
                'type'          => 'payment',
                'amount'        => $invoice->amount - $feeAmount,
                'fee'           => $feeAmount, // Total fee
                'total_amount'  => $invoice->amount,
                'status'        => 'completed', // Set status to completed
                'description'   => $request->description ?? 'مدفوعات للفاتورة رقم ' . $invoice->id,
                'reference_id'  => $invoice->id, // Reference to the invoice
            ]);
            $systemFeesAccount = PaymentSystemAccount::where('name', 'System Fees Account')->first();
            $fatorahAccount = PaymentSystemAccount::where('name', 'System fatorah Account')->first();
            // Create fee records and assign to the two system accounts
            Fee::create([
                'transaction_id' => $transaction->id,
                'amount'         => $systemFeesAccountAmount,
                'payment_system_account_id' => $systemFeesAccount->id,
            ]);

            Fee::create([
                'transaction_id' => $transaction->id,
                'amount'         => $fatorahAccountAmount,
                'payment_system_account_id' => $fatorahAccount->id,
            ]);
            $systemFeesAccount->update([
                'balance' => $systemFeesAccount->balance + $systemFeesAccountAmount
            ]);

            $fatorahAccount->update([
                'balance' => $fatorahAccount->balance + $fatorahAccountAmount
            ]);

            $user->update([
                'balance' => $user->balance - $invoice->amount
            ]);

            $bill->update([
                'amount' => $bill->amount + $invoice->amount,
                'due_date' => now(),
            ]);

            // Commit the transaction to the database
            DB::commit();

            return response()->json(['message' => 'Invoice and transaction created successfully', 'invoice' => $invoice, 'transaction' => $transaction], 201);
        } catch (\Exception $e) {
            // Rollback in case of error
            DB::rollBack();
            return response()->json(['error' => 'Failed to create invoice: ' . $e->getMessage()], 500);
        }
    }


    /**
     * View all refunds.
     * @return \Illuminate\Http\JsonResponse
     */
    public function listRefunds()
    {
        $refunds = Refund::with(['transaction', 'user', 'thirdPartyApp'])->get();

        return response()->json($refunds);
    }

    /**
     * Confirm a refund.
     * @param int $refundId
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirmRefund($refundId)
    {
        $refund = Refund::with('transaction')->find($refundId);

        if (!$refund) {
            return response()->json(['message' => 'Refund not found'], 404);
        }

        if ($refund->status !== 'pending') {
            return response()->json(['message' => 'Refund has already been processed'], 400);
        }

        $transaction = $refund->transaction;

        // Begin a database transaction
        DB::beginTransaction();

        try {
            // Update refund status
            $refund->status = 'completed';
            $refund->save();

            // Update transaction status
            $transaction->status = 'refunded';
            $transaction->save();

            // Adjust user's balance
            $user = $refund->user;
            $user->balance += $refund->amount;
            $user->save();

            // Adjust third-party app's balance
            if ($refund->thirdPartyApp) {
                $thirdPartyApp = $refund->thirdPartyApp;
                $thirdPartyApp->balance -= $refund->amount;
                $thirdPartyApp->save();
            }

            // Adjust system fee balance if fees are refunded
            if ($refund->fee_refunded > 0) {
                $systemAccount = PaymentSystemAccount::first();
                $systemAccount->balance -= $refund->fee_refunded;
                $systemAccount->save();
            }

            // Commit the transaction
            DB::commit();

            return response()->json(['message' => 'Refund confirmed successfully', 'refund' => $refund], 200);
        } catch (\Exception $e) {
            // Rollback on error
            DB::rollBack();
            return response()->json(['message' => 'An error occurred while processing the refund', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Additional method: View all bills.
     * @return \Illuminate\Http\JsonResponse
     */
    public function listBills()
    {
        $bills = Bill::with('user')->get();

        return response()->json($bills);
    }

    public function listUserBills(User $user)
    {
        $bills = Bill::where('user_id', $user->id)->get();

        return response()->json($bills);
    }

    public function getBill(Bill $bill)
    {
        $bill->load('invoice', 'user');

        return response()->json($bill);
    }

    /**
     * Additional method: View all invoices.
     * @return \Illuminate\Http\JsonResponse
     */
    public function listInvoices()
    {
        $invoices = Invoice::with(['user', 'bill'])->paginate(15);

        return response()->json($invoices);
    }

    public function listUserInvoices(User $user)
    {
        $invoices = Invoice::where('user_id', $user->id)->get();

        return response()->json($invoices);
    }

    public function getInvoice(Invoice $invoice)
    {
        $invoice->load('transaction', 'user', 'bill');

        return response()->json($invoice);
    }

    /**
     * Additional method: View all transactions.
     * @return \Illuminate\Http\JsonResponse
     */
    public function listTransactions()
    {
        $transactions = Transaction::with(['user', 'thirdPartyApp'])->get();

        return response()->json($transactions);
    }

    public function listUserTransactions(User $user)
    {
        $transactions = Transaction::where('user_id', $user->id)->get();

        return response()->json($transactions);
    }

    /**
     * Increase the balance of a user when they deposit money at a store.
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function increaseUserBalance(Request $request, User $user)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'amount'       => 'required|numeric|min:0.01',
            'description'  => 'nullable|string|max:255',
        ], [
            'amount.required'      => 'المبلغ مطلوب',
            'amount.numeric'       => 'المبلغ يجب أن يكون رقمًا',
            'amount.min'           => 'المبلغ يجب أن يكون أكبر من صفر',
            'description.string'   => 'الوصف يجب أن يكون نصًا',
            'description.max'      => 'الوصف لا يجب أن يتجاوز 255 حرفًا',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Begin database transaction
        DB::beginTransaction();

        try {
            // Increase user's balance
            $user->balance += $request->amount;
            $user->save();

            // Record the transaction
            $transaction = Transaction::create([
                'user_id'       => $user->id,
                'type'          => 'deposit',
                'amount'        => $request->amount,
                'fee'           => 0, // No fee for deposits
                'total_amount'  => $request->amount,
                'status'        => 'completed',
                'description'   => $request->description ?? 'إيداع من قبل المسؤول',
            ]);

            DB::commit();

            return response()->json($transaction);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء زيادة رصيد المستخدم: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Additional method: Update user details.
     * @param Request $request
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateUser(Request $request, $userId)
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'     => 'sometimes|required|string|max:255',
            // 'email'    => 'sometimes|required|email|unique:users,email,' . $user->id,
            'phone'    => 'sometimes|required|string|unique:users,phone,' . $user->id,
            'ids'    => 'sometimes|required|string|unique:users,ids,' . $user->id,
            'password' => 'nullable|string|min:6',
            // 'role'     => 'sometimes|required|in:user,third_party_app',
            // Additional fields if necessary
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->fill($request->only(['name', 'phone', 'ids']));

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json(['message' => 'User updated successfully', 'user' => $user], 200);
    }

    /**
     * Additional method: Delete a user.
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteUser($userId)
    {
        $user = User::find($userId);

        if (!$user || $user->isAdmin()) {
            return response()->json(['message' => 'User not found or cannot delete admin'], 404);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully'], 200);
    }

    public function getSummary()
    {
        // Retrieve system account balances
        $systemAccounts = DB::table('payment_system_accounts')
            ->select('name', 'balance')
            ->get();

        // Arabic names for the system accounts
        $systemAccountNames = [
            'System Fees Account' => 'حساب رسوم النظام',
            'System fatorah Account' => 'حساب فاتورة ',
        ];

        // Replace the English account names with Arabic names
        $systemAccounts = $systemAccounts->map(function ($account) use ($systemAccountNames) {
            $account->name = $systemAccountNames[$account->name] ?? $account->name;
            return $account;
        });

        // Calculate the total balance (system accounts + user balances)
        $totalSystemBalance = DB::table('payment_system_accounts')->sum('balance');
        $totalUserBalance = DB::table('users')->sum('balance'); // Assuming users table has a 'balance' column
        $overallBalance = $totalSystemBalance + $totalUserBalance;

        // Get the total number of transactions, users, and bills
        $totalTransactions = Transaction::count(); // Assuming you have a Transaction model
        $totalUsers = User::count();
        $totalBills = Bill::count(); // Assuming you have a Bill model

        // Return the data
        return response()->json([
            'system_accounts' => $systemAccounts,
            'overall_balance' => $overallBalance,
            'total_transactions' => $totalTransactions,
            'total_users' => $totalUsers,
            'total_bills' => $totalBills,
        ]);
    }
}

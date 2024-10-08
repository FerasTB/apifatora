<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Middleware to ensure only authenticated users can access these methods.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get user profile information.
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile()
    {
        $user = auth()->user();

        // Optionally load relationships if needed
        // $user->load('bills', 'invoices', 'transactions', 'refunds');

        return response()->json($user);
    }

    /**
     * Update user password.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password'      => 'required|string',
            'new_password'          => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth()->user();

        // Check if current password matches
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['errors' => ['current_password' => ['Current password is incorrect.']]], 422);
        }

        // Update password
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password updated successfully.']);
    }

    /**
     * View user's bills.
     * @return \Illuminate\Http\JsonResponse
     */
    public function viewBills()
    {
        $user = Auth::user();
        $bills = $user->bills()->get();

        return response()->json($bills);
    }

    /**
     * View user's invoices.
     * @return \Illuminate\Http\JsonResponse
     */
    public function viewInvoices()
    {
        $user = Auth::user();
        $invoices = $user->invoices()->get();

        return response()->json($invoices);
    }

    /**
     * View user's transactions.
     * @return \Illuminate\Http\JsonResponse
     */
    public function viewTransactions()
    {
        $user = Auth::user();
        $transactions = $user->transactions()->with('thirdPartyApp')->get();

        return response()->json($transactions);
    }

    /**
     * View user's refunds.
     * @return \Illuminate\Http\JsonResponse
     */
    public function viewRefunds()
    {
        $user = Auth::user();
        $refunds = $user->refunds()->with('transaction')->get();

        return response()->json($refunds);
    }

    /**
     * Request a refund for a transaction.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestRefund(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|exists:transactions,id',
            'reason'         => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $transaction = Transaction::where('id', $request->transaction_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found.'], 404);
        }

        // Check if transaction is refundable
        if (!$transaction->isRefundable()) {
            return response()->json(['message' => 'This transaction is not eligible for a refund.'], 400);
        }

        // Check if a refund has already been requested
        if ($transaction->refund) {
            return response()->json(['message' => 'A refund has already been requested for this transaction.'], 400);
        }

        // Create a refund request
        $refund = Refund::create([
            'transaction_id'    => $transaction->id,
            'user_id'           => $user->id,
            'third_party_app_id' => $transaction->third_party_app_id,
            'amount'            => $transaction->amount,
            'fee_refunded'      => $transaction->fee, // Adjust based on your fee refund policy
            'status'            => 'pending',
            'reason'            => $request->reason,
        ]);

        // Optionally notify admin about the refund request
        // ...

        return response()->json(['message' => 'Refund request submitted successfully.', 'refund' => $refund], 201);
    }

    /**
     * Additional method: Update user profile information (excluding password).
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|unique:users,phone,' . Auth::id(),
            // Add other fields as necessary
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $user->fill($request->only(['name', 'phone']));
        $user->save();

        return response()->json(['message' => 'Profile updated successfully.', 'user' => $user]);
    }

    /**
     * Additional method: Deposit funds into the user's wallet.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function depositFunds(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            // Add additional validation if necessary
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Implement deposit logic (e.g., integrate with a payment gateway)
        // For this example, we'll simulate a deposit

        $user = Auth::user();
        $user->balance += $request->amount;
        $user->save();

        // Record the transaction
        Transaction::create([
            'user_id'           => $user->id,
            'type'              => 'deposit',
            'amount'            => $request->amount,
            'fee'               => 0,
            'total_amount'      => $request->amount,
            'status'            => 'completed',
            'description'       => 'Wallet deposit',
        ]);

        return response()->json(['message' => 'Funds deposited successfully.', 'balance' => $user->balance]);
    }

    /**
     * Additional method: Withdraw funds from the user's wallet.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function withdrawFunds(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            // Add additional validation if necessary
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();

        if ($user->balance < $request->amount) {
            return response()->json(['message' => 'Insufficient funds.'], 400);
        }

        // Implement withdrawal logic (e.g., integrate with a payment gateway)
        // For this example, we'll simulate a withdrawal

        $user->balance -= $request->amount;
        $user->save();

        // Record the transaction
        Transaction::create([
            'user_id'           => $user->id,
            'type'              => 'withdrawal',
            'amount'            => $request->amount,
            'fee'               => 0,
            'total_amount'      => $request->amount,
            'status'            => 'completed',
            'description'       => 'Wallet withdrawal',
        ]);

        return response()->json(['message' => 'Funds withdrawn successfully.', 'balance' => $user->balance]);
    }
}

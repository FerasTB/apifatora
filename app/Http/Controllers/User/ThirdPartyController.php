<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ThirdPartyController extends Controller
{
    /**
     * Middleware to ensure only authenticated third-party apps can access these methods.
     */
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'checkRole:third_party_app']);
    }

    /**
     * Initiate a transaction on behalf of a user.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function initiateTransaction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_phone'        => 'required|string|exists:users,phone',
            'amount'            => 'required|numeric|min:0.01',
            'description'       => 'nullable|string',
            'refundable_until'  => 'required|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $thirdPartyApp = Auth::user();

        // Find the user by email
        $user = User::where('phone', $request->user_phone)->first();

        // Check if the user has sufficient balance
        $feePercentage = $thirdPartyApp->fee_percentage ?? config('app.default_fee_percentage', 0);
        $fee = round($request->amount * $feePercentage / 100, 2);
        $totalAmount = $request->amount + $fee;

        if ($user->balance < $totalAmount) {
            return response()->json(['message' => 'User has insufficient funds.'], 400);
        }

        // Begin a database transaction
        DB::beginTransaction();

        try {
            // Deduct the total amount from the user's balance
            $user->balance -= $totalAmount;
            $user->save();

            // Credit the amount (excluding fee) to the third-party app's balance
            $thirdPartyApp->balance += $request->amount;
            $thirdPartyApp->save();

            // Credit the fee to the system's account
            $systemAccount = \App\Models\PaymentSystemAccount::first();
            $systemAccount->balance += $fee;
            $systemAccount->save();

            // Record the transaction
            $transaction = Transaction::create([
                'user_id'           => $user->id,
                'third_party_app_id' => $thirdPartyApp->id,
                'type'              => 'payment',
                'amount'            => $request->amount,
                'fee'               => $fee,
                'total_amount'      => $totalAmount,
                'status'            => 'completed',
                'description'       => $request->description,
                'refundable_until'  => $request->refundable_until,
            ]);

            // Record the fee
            \App\Models\Fee::create([
                'transaction_id' => $transaction->id,
                'amount'         => $fee,
            ]);

            // Commit the transaction
            DB::commit();

            // Optionally notify the user
            // $user->notify(new \App\Notifications\PaymentProcessed($transaction));

            return response()->json(['message' => 'Transaction completed successfully.', 'transaction' => $transaction], 201);
        } catch (\Exception $e) {
            // Rollback on error
            DB::rollBack();
            return response()->json(['message' => 'An error occurred while processing the transaction.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * View transactions initiated by the third-party app.
     * @return \Illuminate\Http\JsonResponse
     */
    public function viewTransactions()
    {
        $thirdPartyApp = Auth::user();
        $transactions = Transaction::where('third_party_app_id', $thirdPartyApp->id)
            ->with('user')
            ->get();

        return response()->json($transactions);
    }

    /**
     * Request a refund for a transaction (if applicable).
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

        $thirdPartyApp = Auth::user();
        $transaction = Transaction::where('id', $request->transaction_id)
            ->where('third_party_app_id', $thirdPartyApp->id)
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
            'user_id'           => $transaction->user_id,
            'third_party_app_id' => $thirdPartyApp->id,
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
     * Additional method: View balance and account details.
     * @return \Illuminate\Http\JsonResponse
     */
    public function accountDetails()
    {
        $thirdPartyApp = Auth::user();

        return response()->json([
            'balance' => $thirdPartyApp->balance,
            'email'   => $thirdPartyApp->email,
            'name'    => $thirdPartyApp->name,
            'phone'    => $thirdPartyApp->phone,
            'ids'    => $thirdPartyApp->ids,
            // Add other account details as needed
        ]);
    }

    /**
     * Additional method: Update account information.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'sometimes|required|string|max:255',
            'email'    => 'sometimes|required|email|unique:users,email,' . Auth::id(),
            'password' => 'nullable|string|min:6|confirmed',
            // Add other fields as necessary
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $thirdPartyApp = Auth::user();
        $thirdPartyApp->fill($request->only(['name', 'email']));

        if ($request->filled('password')) {
            $thirdPartyApp->password = Hash::make($request->password);
        }

        $thirdPartyApp->save();

        return response()->json(['message' => 'Account updated successfully.', 'third_party_app' => $thirdPartyApp]);
    }
}

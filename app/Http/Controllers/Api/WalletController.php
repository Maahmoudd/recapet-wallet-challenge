<?php

namespace App\Http\Controllers\Api;

use App\Actions\Wallet\DepositAction;
use App\Actions\Wallet\GetTransactionHistoryAction;
use App\Actions\Wallet\TransferAction;
use App\Actions\Wallet\WithdrawAction;
use App\Http\Requests\Wallet\DepositRequest;
use App\Http\Requests\Wallet\TransactionHistoryRequest;
use App\Http\Requests\Wallet\TransferRequest;
use App\Http\Requests\Wallet\WithdrawRequest;
use App\Http\Resources\TransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class WalletController extends BaseApiController
{
    public function deposit(DepositRequest $request, DepositAction $action): JsonResponse
    {
        $user = Auth::user();
        $result = $action->execute($user, $request->validated());

        return $this->successResponse([
            'transaction' => new TransactionResource($result['transaction']),
            'new_balance' => $result['new_balance'],
            'previous_balance' => $result['previous_balance'],
            'message' => 'Deposit completed successfully',
        ], 'Deposit completed successfully');
    }

    public function withdraw(WithdrawRequest $request, WithdrawAction $action): JsonResponse
    {
        $user = Auth::user();
        $result = $action->execute($user, $request->validated());

        return $this->successResponse([
            'transaction' => new TransactionResource($result['transaction']),
            'new_balance' => $result['new_balance'],
            'previous_balance' => $result['previous_balance'],
            'withdrawn_amount' => $result['withdrawn_amount'],
            'status' => 'completed',
        ], 'Withdrawal completed successfully');
    }

    public function transfer(TransferRequest $request, TransferAction $action): JsonResponse
    {
        $user = Auth::user();
        $result = $action->execute($user, $request->validated());

        return $this->successResponse([
            'transaction' => new TransactionResource($result['transaction']),
            'transfer_details' => [
                'amount' => $result['transfer_amount'],
                'fee' => $result['fee_amount'],
                'total_deducted' => $result['total_deducted'],
                'reference' => $result['reference'],
            ],
            'balances' => [
                'sender_new_balance' => $result['sender_new_balance'],
                'recipient_new_balance' => $result['recipient_new_balance'],
            ],
            'status' => 'completed',
        ], 'Transfer completed successfully');
    }

    public function getTransactionHistory(TransactionHistoryRequest $request, GetTransactionHistoryAction $action): JsonResponse
    {
        $user = Auth::user();
        $result = $action->execute($user, $request->validated());

        return $this->successResponse([
            'transactions' => TransactionResource::collection($result['transactions']),
            'pagination' => [
                'current_page' => $result['transactions']->currentPage(),
                'last_page' => $result['transactions']->lastPage(),
                'per_page' => $result['transactions']->perPage(),
                'total' => $result['transactions']->total(),
                'from' => $result['transactions']->firstItem(),
                'to' => $result['transactions']->lastItem(),
            ],
            'summary' => $result['summary'],
            'current_balance' => number_format($result['wallet_balance'], 2, '.', ''),
        ], 'Transaction history retrieved successfully');
    }
}

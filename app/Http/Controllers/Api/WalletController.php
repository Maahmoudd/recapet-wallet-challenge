<?php

namespace App\Http\Controllers\Api;

use App\Actions\Wallet\DepositAction;
use App\Actions\Wallet\WithdrawAction;
use App\Http\Requests\Wallet\DepositRequest;
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
}

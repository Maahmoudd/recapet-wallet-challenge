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
        return rescue(function () use ($request, $action) {
            $user = Auth::user();
            $result = $action->execute($user, $request->validated());

            return $this->successResponse([
                'transaction' => new TransactionResource($result['transaction']),
                'new_balance' => $result['new_balance'],
                'previous_balance' => $result['previous_balance'],
                'message' => 'Deposit completed successfully',
            ], 'Deposit completed successfully');

        }, function (\Throwable $e) {
            if ($e->getMessage() === 'Duplicate transaction detected') {
                return $this->errorResponse('Duplicate transaction detected', 409);
            }
            if ($e->getMessage() === 'Wallet is not active') {
                return $this->errorResponse('Wallet is not active', 403);
            }

            logger()->error('Deposit failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse('Deposit failed', 500);
        });
    }

    public function withdraw(WithdrawRequest $request, WithdrawAction $action): JsonResponse
    {
        return rescue(function () use ($request, $action) {
            $user = Auth::user();
            $result = $action->execute($user, $request->validated());

            return $this->successResponse([
                'transaction' => new TransactionResource($result['transaction']),
                'new_balance' => $result['new_balance'],
                'previous_balance' => $result['previous_balance'],
                'withdrawn_amount' => $result['withdrawn_amount'],
                'status' => 'completed',
            ], 'Withdrawal completed successfully');

        }, function (\Throwable $e) {
            if ($e->getMessage() === 'Duplicate transaction detected') {
                return $this->errorResponse('Duplicate transaction detected', 409);
            }

            if ($e->getMessage() === 'Insufficient balance') {
                return $this->errorResponse('Insufficient balance for withdrawal', 400, [
                    'error_code' => 'INSUFFICIENT_BALANCE',
                    'status' => 'failed'
                ]);
            }

            if ($e->getMessage() === 'Wallet is not active') {
                return $this->errorResponse('Wallet is not active', 403, [
                    'error_code' => 'WALLET_INACTIVE',
                    'status' => 'failed'
                ]);
            }

            logger()->error('Withdrawal failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse('Withdrawal failed', 500, [
                'status' => 'failed'
            ]);
        });
    }
}

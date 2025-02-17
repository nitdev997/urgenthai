<?php

namespace Botble\Marketplace\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Botble\Driver\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AccountController extends Controller
{

    // index
    public function index()
    {
        $user = auth()->user();
        $accounts = Account::where('user_id', $user->id)->where('user_type', 'vendor')->orderBy('account_type')->get();
        return response()->json(['success' => true, 'accounts' => $accounts]);
    }

    // create
    public function create(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'account_type' => 'required|in:bank,upi',
            'bank_name' => 'required_if:account_type,bank|nullable|string',
            'account_holder_name' => 'required_if:account_type,bank|nullable|string',
            'account_number' => 'required_if:account_type,bank|nullable|string',
            'ifsc' => 'required_if:account_type,bank|nullable|string',
            'upi_id' => 'required_if:account_type,upi|nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 422);
        }

        // check if exists
        if ($request->account_type == 'bank') {
            $getAccount = Account::where('user_id', $user->id)->where('user_type', 'vendor')->where('account_number', $request->account_number)->where('ifsc', $request->ifsc)->first();
        } else if ($request->account_type == 'upi') {
            $getAccount = Account::where('user_id', $user->id)->where('user_type', 'vendor')->where('upi_id', $request->upi_id)->first();
        }

        if ($getAccount) {
            return response()->json(['success' => false, 'message' => 'Account already exists!'], 422);
        }

        // create account
        $account = Account::create([
            'account_type' => $request->account_type,
            'bank_name' => $request->bank_name,
            'account_holder_name' => $request->account_holder_name,
            'account_number' => $request->account_number,
            'ifsc' => $request->ifsc,
            'upi_id' => $request->upi_id,
            'user_id' => $user->id,
            'user_type' => 'vendor'
        ]);

        // update default account
        Account::where('user_id', $user->id)->where('user_type', 'vendor')->update([
            'default' => false
        ]);

        $account->default = true;
        $account->save();

        return response()->json(['success' => true, 'message' => 'Account created successfully.', 'data' => $account], 201);
    }

    // set default account
    public function setDefault($id)
    {
        $user = auth()->user();
        $account = Account::where('user_id', $user->id)->where('user_type', 'vendor')->where('id', $id)->first();

        if (!$account) {
            return response()->json(['success' => false, 'message' => 'Account not found!'], 404);
        }

        // update default account
        Account::where('user_id', $user->id)->where('user_type', 'vendor')->update([
            'default' => false
        ]);

        $account->default = true;
        $account->save();

        return response()->json(['success' => true, 'message' => 'Account set as default successfully.'], 200);
    }

    // update
    public function update($id, Request $request)
    {
        $user = auth()->user();
        $account = Account::where('user_id', $user->id)->where('user_type', 'vendor')->where('id', $id)->first();

        if (!$account) {
            return response()->json(['success' => false, 'message' => 'Account not found!'], 404);
        }

        $validator = Validator::make($request->all(), [
            'account_type' => 'required|in:bank,upi',
            'bank_name' => 'required_if:account_type,bank|nullable|string',
            'account_holder_name' => 'required_if:account_type,bank|nullable|string',
            'account_number' => 'required_if:account_type,bank|nullable|string',
            'ifsc' => 'required_if:account_type,bank|nullable|string',
            'upi_id' => 'required_if:account_type,upi|nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 422);
        }

        // check if exists
        if ($request->account_type == 'bank') {
            $getAccount = Account::where('user_id', $user->id)->where('user_type', 'vendor')->where('account_number', $request->account_number)->where('ifsc', $request->ifsc)->where('id', '!=', $id)->first();
        } else if ($request->account_type == 'upi') {
            $getAccount = Account::where('user_id', $user->id)->where('user_type', 'vendor')->where('upi_id', $request->upi_id)->where('id', '!=', $id)->first();
        }

        if ($getAccount) {
            return response()->json(['success' => false, 'message' => 'Account already exists!'], 422);
        }

        // update account
        $account->update([
            'account_type' => $request->account_type,
            'bank_name' => $request->bank_name,
            'account_holder_name' => $request->account_holder_name,
            'account_number' => $request->account_number,
            'ifsc' => $request->ifsc,
            'upi_id' => $request->upi_id
        ]);

        return response()->json(['success' => true, 'message' => 'Account updated successfully.'], 200);
    }

    // delete account
    public function destroy($id) {
        $user = auth()->user();
        $account = Account::where('user_id', $user->id)->where('user_type', 'vendor')->where('id', $id)->first();

        if (!$account) {
            return response()->json(['success' => false, 'message' => 'Account not found!'], 404);
        }

        // check if account is default
        if ($account->default) {
            return response()->json(['success' => false, 'message' => 'Cannot delete default account!'], 400);
        }

        $account->delete();

        return response()->json(['success' => true, 'message' => 'Account deleted successfully.'], 200);
    }
}

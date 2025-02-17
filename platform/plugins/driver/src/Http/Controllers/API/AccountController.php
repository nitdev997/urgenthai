<?php

namespace Botble\Driver\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Botble\Driver\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Razorpay\Api\Api;

class AccountController extends Controller
{

    // index
    public function index()
    {
        $user = auth()->user();
        $accounts = Account::where('user_id', $user->id)->where('user_type', 'driver')->orderBy('account_type')->get();
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
            $getAccount = Account::where('user_id', $user->id)->where('user_type', 'driver')->where('account_number', $request->account_number)->where('ifsc', $request->ifsc)->first();
        } else if ($request->account_type == 'upi') {
            $getAccount = Account::where('user_id', $user->id)->where('user_type', 'driver')->where('upi_id', $request->upi_id)->first();
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
            'user_type' => 'driver'
        ]);

        // update default account
        Account::where('user_id', $user->id)->where('user_type', 'driver')->update([
            'default' => false
        ]);

        $account->default = true;
        $account->save();

        // create a fund_account
        $api = new Api(config('services.razorpay.key'), config('services.razorpay.secret'));
        $key = config('services.razorpay.key');
        $secret = config('services.razorpay.secret');

        // create contact
        $contact = Http::withBasicAuth($key, $secret)->post('https://api.razorpay.com/v1/contacts', [
            "name" => $account->account_holder_name,
            "email" => $user->email,
            "contact" => $user->phone,
            "type" => "employee",
        ]);

        $contact = $contact->json();
        $contact_id = $contact['id'];

        if ($request->account_type == 'bank') {
            $fundAccount = Http::withBasicAuth($key, $secret)->post('https://api.razorpay.com/v1/fund_accounts', [
                "contact_id" => $contact_id,
                "account_type" => "bank_account",
                "bank_account" => [
                    "name" => $account->account_holder_name,
                    "ifsc" => $account->ifsc,
                    "account_number" => $account->account_number
                ]
            ]);
        }

        $fundAccount = $fundAccount->json();
        if(isset($fundAccount['error'])){
            return response()->json(['success' => false, 'message' => $fundAccount['error']['description']], 422);
        }
        $account->fund_account_id = $fundAccount['id'];
        $account->save();

        return response()->json(['success' => true, 'message' => 'Account created successfully.', 'data' => $account], 201);
    }

    // set default account
    public function setDefault($id)
    {
        $user = auth()->user();
        $account = Account::where('user_id', $user->id)->where('user_type', 'driver')->where('id', $id)->first();

        if (!$account) {
            return response()->json(['success' => false, 'message' => 'Account not found!'], 404);
        }

        // update default account
        Account::where('user_id', $user->id)->where('user_type', 'driver')->update([
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
        $account = Account::where('user_id', $user->id)->where('user_type', 'driver')->where('id', $id)->first();

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
            $getAccount = Account::where('user_id', $user->id)->where('user_type', 'driver')->where('account_number', $request->account_number)->where('ifsc', $request->ifsc)->where('id', '!=', $id)->first();
        } else if ($request->account_type == 'upi') {
            $getAccount = Account::where('user_id', $user->id)->where('user_type', 'driver')->where('upi_id', $request->upi_id)->where('id', '!=', $id)->first();
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
    public function destroy($id)
    {
        $user = auth()->user();
        $account = Account::where('user_id', $user->id)->where('user_type', 'driver')->where('id', $id)->first();

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

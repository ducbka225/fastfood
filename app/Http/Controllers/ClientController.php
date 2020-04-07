<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use App\product;
use App\User;
use App\Category;

class ClientController extends Controller
{
	public function getIndex(){
		$food = Product::join('category', 'category.id', 'product.id_category')
						->where('category.type', 'Đồ Ăn')
						->paginate(8);
		$drink = Product::join('category', 'category.id', 'product.id_category')
						->where('category.type', 'Đồ Uống')
						->paginate(8);
		return view('client.index', compact('food', 'drink'));
	}

	//Login
	public function getLogin(){
		return view('client.login');
	}

	public function postLogin(Request $req){
		$this->validate(
			$req,
			[
				'email' => 'required',
				'password' => 'required|min:6',

			],
			[
				'email.required' => 'Vui lòng nhập Tài Khoản',
				'email.required' => 'Vui lòng nhập mật khẩu',
				'password.min' => 'Mật khẩu có ít nhất 6 ký tự',
			]
		);

		$user = array('email' => $req->email, 'password' => $req->password, 'role' => 0);

		if (Auth::attempt($user)) {
			return redirect('/');
		} 
		else {
			return redirect()->back()->with('error', 'Tài khoản hoặc mật khẩu không chính xác.');
		}
	}

	public function getLogout(){
		Auth::logout();
		return redirect('/');
	}

	public function postRegister(Request $req){
		$this->validate(
			$req,
			[
				'email' => 'unique:users,email',
				'password' => 'required|min:6|regex:(^[A-Za-z0-9_.]+$)',
			],
			[
				'email.unique' => 'Email đã tồn tại',
				'password.required' => 'Vui lòng nhập mật khẩu',
				'password.min' => 'Mật khẩu có ít nhất 6 ký tự',
				'password.regex' => 'Đăng Ký Thất Bại. Mật Khẩu Chứa Ký Tự Không Hợp Lệ',
			]
		);

		if($req->password != $req->re_password){
			return redirect()->back()->with('error', 'Mật khẩu nhập lại không khớp.');
		}
		try {
			$user = new User();
			$user->name = $req->name;
			$user->email = $req->email;
			$user->password = \Hash::make($req->password);
			$user->phone = $req->phone;
			$user->address = $req->address;
			$user->avatar = "avatar.png";
			$user->role = 0;
			$user->save();
			return redirect()->back()->with('message', 'Đăng Ký thành công.');
		} catch (Exception $e) {
			return redirect()->back()->with('error', 'Có lỗi hệ thống vui lòng thử lại.');
		}
	}

	//product details
	public function getProductDetails($id){
		$product = Product::where('id', $id)->first();
		$relate_product = Product::where('id_category', $product->id_category)->paginate(4);
		$category = Category::orderBy('created_at', 'desc')->take(10)->get();
		return view('client.product.details', compact('product', 'relate_product', 'category'));
	}
}

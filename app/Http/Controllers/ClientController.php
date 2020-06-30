<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use App\product;
use App\User;
use App\Category;
use App\Comment;
use App\Cart;
use App\Checkout;
use App\Customer;
use App\Order;
use App\OrderDetails;
use App\Table;
use App\BookTable;
use Session;
use DB;
use Carbon\Carbon;
use App\NL_CheckOutV3;
use Illuminate\Support\Facades\Hash;
use Mail;

class ClientController extends Controller
{
	public function getIndex(){
		$food = Product::join('category', 'category.id', 'product.id_category')
		->select('product.*')
		->where('category.type', 'Đồ Ăn')
		->paginate(8, ['*'], 'food');
		$drink = Product::join('category', 'category.id', 'product.id_category')
		->select('product.*')
		->where('category.type', 'Đồ Uống')
		->paginate(8, ['*'], 'drink');
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
			if($req->reg == 0){
				return redirect('/');
			}
			else{
				return redirect()->back();
			}
		} 
		else {
			return redirect()->back()->with('error', 'Tài khoản hoặc mật khẩu không chính xác.');
		}
	}

	public function getLogout(Request $req){
		Auth::logout();
		$req->session()->forget('cart');
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
		$relate_product = Product::where('id_category', $product->id_category)
		->where('delete_flag', 0)->paginate(4);
		$category = Category::orderBy('created_at', 'desc')->take(20)->get();
		$comment = Comment::where('id_product', $id)
		->where('delete_flag', 0)
		->get();
		return view('client.product.details', compact('product', 'relate_product', 'category', 'comment'));
	}

	//commment
	public function postComment($id, Request $req){
		$comment = new Comment();
		$comment->content = $req->content;
		$comment->id_product = $id;
		$comment->id_user = Auth::user()->id;
		$comment->rate = 5;
		$comment->save();
		return redirect()->back()->with('message', 'Comment thành công');
	}

	//add to cart
	public function getAddtoCart(Request $req, $id){
        //check exits product in cart
		$product = Product::find($id);
		$oldCart = Session('cart')?Session::get('cart'):null;
		$cart = new Cart($oldCart);
		$cart->add($product, $id);
		$req->Session()->put('cart', $cart);
		return redirect()->back();
	}

	//add mulitple to cart
	public function getAddMultipletoCart(Request $req, $id){
		$unit = $req->unit;
        //check exits product in cart
		$product = Product::find($id);
		$oldCart = Session('cart')?Session::get('cart'):null;
		$cart = new Cart($oldCart);
		$cart->addMulti($product, $id, $unit);
		$req->Session()->put('cart', $cart);
		return redirect()->back();
	}

	//update to cart
	public function getUpdateCart(Request $req){
		$unit = $req->unit;
		$id = $req->id;
        //check exits product in cart
		$product = Product::find($id);
		$oldCart = Session('cart')?Session::get('cart'):null;
		$cart = new Cart($oldCart);
		$cart->update($product, $id, $unit);
		$req->Session()->put('cart', $cart);
		return redirect()->back();
	}

	public function getDelItemCart($id){
		$oldCart = Session::has('cart')?Session::get('cart'):null;
		$cart = new Cart($oldCart);
		$cart->removeItem($id);
		if(count($cart->items)>0){
			Session::put('cart', $cart);
		}
		else{
			Session::forget('cart');
		}

		return redirect()->back();
	}

    //checkout
	public function getCart(){
		$category = Category::orderBy('created_at', 'desc')->take(10)->get();
		return view('client.cart', compact('category'));
	}

    //search product
	public function getsearchProduct(Request $req){
		$product = Product::where('product_name', 'LIKE', '%' .$req->key. '%')->paginate(6);
		$key = $req->key;
		$category = Category::orderBy('created_at', 'desc')->take(20)->get();
		return  view('client.product.search', compact('product', 'key', 'category'));
	}

    // product by category
	public function getProductByCategory($id){
		$product = Product::where('id_category', $id)->paginate(6);
		$category = Category::orderBy('created_at', 'desc')->take(20)->get();
		$key = Category::where('id', $id)->first();
		return  view('client.product.product-by-category', compact('product', 'key', 'category'));
	}

    // check out
	public function getCheckout(Request $req){
		$checkout = new Checkout();
		$checkout->total_price = $req->total_price;
		$checkout->book_type = $req->book_type;
		Session::put('checkout', $checkout);
		if($req->book_type == '1'){
			return view('client.checkout.online');
		}
		else{
			return view('client.checkout.offline');
		}
	}

    // accept order
	public function postAcceptOrderOnline(Request $req){
		$totalPrice = Session::get('checkout')->total_price;
		// Ngân Lượng
		$urlApi = env('URL_API');
		$receive = env('RECEIVER');
		$mechantID  = env('MERCHANT_ID');
		$mechantPass = env('MERCHANT_PASS');
		$nlcheckout = new NL_CheckOutV3($mechantID, $mechantPass, $receive, $urlApi);
		$return_url = 'http://localhost:8000/checkout-success';
		// $return_url = 'http://localhost/nganluong.vn/checkoutv3/payment_success.php';
		$cancel_url = 'http://localhost:8000/checkout?total_price=' . $totalPrice . '&book_type=1';
		$payment_type ='';
		$discount_amount =0;
		$order_description='';
		$tax_amount=0;
		$fee_shipping=0;
		$bankCode = $req->bankcode;
		$order_code ="macode_".time();
		$array_items[0]= array('item_name1' => 'FastFood',
					 'item_quantity1' => 1,
					 'item_amount1' => $totalPrice,
					 'item_url1' => 'http://nganluong.vn/');
		$array_items=array();
					 	
		$user = User::where('id', Auth::user()->id)->first();
		// transaction
		DB::beginTransaction();
		try {
			// save customer
			$customer = new Customer();
			$customer->name = $req->name;
			$customer->email = $req->email;
			$customer->phone = $req->phone;
			$customer->address = $req->address;
			$customer->note = $req->note;
			$customer->save();

			// save order
			$order = new Order();
			$order->total_price = $totalPrice;
			if($req->option_payment == 2){
				$order->payment = 'Thanh Toán khi nhận hàng';
				$order->status = 0;
			}
			else if($req->option_payment == 'ATM_ONLINE'){
				$nl_result= $nlcheckout->BankCheckout($order_code,$totalPrice,$bankCode,$payment_type, $req->note,$tax_amount,
					$fee_shipping,$discount_amount,$return_url,$cancel_url,$req->name,$req->email,$req->phone, 
					$req->address, $array_items);
				if ($nl_result->error_code =='00'){
					$order->payment = 'Thanh Toán ATM';
					$order->status = 1;
				}
			}
			else if($req->option_payment == 'VISA'){
				$nl_result = $nlcheckout->VisaCheckout($order_code, $totalPrice, $payment_type, $req->note, $tax_amount, $fee_shipping, $discount_amount, $return_url, $cancel_url, $req->name, $req->email, $req->phone, 
					$req->address, $array_items, $bankCode);
				if ($nl_result->error_code =='00'){
					$order->payment = 'Thanh Toán Visa';
					$order->status = 1;
				}
			}
			$order->note = $req->note;
			$order->type_order = Session::get('checkout')->book_type;
			$order->delete_flag = 0;
			$order->id_customer = $customer->id;
			$order->save();

			$cart = Session::get('cart');
			foreach($cart->items as $key => $value){
				$orderDetails = new OrderDetails();
				$orderDetails->id_order = $order->id;
				$orderDetails->id_product = $key;
				$orderDetails->unit_price = $value['price']/$value['qty'];
				$orderDetails->quantity = $value['qty'];
				$orderDetails->save();
			}
			Session::forget('cart');
			Session::forget('checkout');
			DB::commit();
			if($req->option_payment != 2){
				return redirect((string)$nl_result->checkout_url);
			}
			Mail::send('mail.temp_mail', array('name'=>$customer->name,'email'=>$customer->email), function($message){
				$message->to(request()->input('email'), request()->input('name'))
				->subject('Đặt Hàng Thành Công!');
				$message->from('fastfoodbka@gmail.com','FastFood');
			});
			return view('client.checkout.complete');
		}
		catch (Exception $e) {
			DB::rollBack();
			return redirect('/')->with('error', 'Có Lỗi Xảy Ra, Vui Lòng Thử Lại!');
		}
	}

	public function postAcceptOrderOffline(Request $req){
		// check table
		$table = Table::where('code', $req->code)->first();
		if(!$table){
			return redirect()->back()->with('error', 'code bàn không tồn tại, vui lòng thử lại');
		}
		// transaction
		DB::beginTransaction();
		try {
			// save customer
			$customer = new Customer();
			$customer->name = $req->name;
			$customer->email = $req->email;
			$customer->phone = $req->phone;
			$customer->address = 'Bàn:'.$table->table_name;
			$customer->note = $req->note;
			$customer->save();

			// save order
			$order = new Order();
			$order->total_price = Session::get('checkout')->total_price;
			$order->payment = 'Thanh Toán khi nhận hàng';
			$order->status = 0;
			$order->note = $req->note;
			$order->type_order = Session::get('checkout')->book_type;
			$order->delete_flag = 0;
			$order->id_customer = $customer->id;
			$order->save();

			$cart = Session::get('cart');
			foreach($cart->items as $key => $value){
				$orderDetails = new OrderDetails();
				$orderDetails->id_order = $order->id;
				$orderDetails->id_product = $key;
				$orderDetails->unit_price = $value['price']/$value['qty'];
				$orderDetails->quantity = $value['qty'];
				$orderDetails->save();
			}
			Session::forget('cart');
			Session::forget('checkout');
			DB::commit();
			return view('client.checkout.complete');
		}
		catch (Exception $e) {
			DB::rollBack();
			return redirect('/')->with('error', 'Có Lỗi Xảy Ra, Vui Lòng Thử Lại!');
		}
	}

	// sale off
	public function getSaleOff(){
		$product = Product::where('promotion_price', '<>', 'null')->paginate(6);
		$category = Category::orderBy('created_at', 'desc')->take(20)->get();
		return  view('client.product.saleoff', compact('product', 'category'));
	}

	// introduce
	public function getIntroduce(){
		$table = Table::where('delete_flag', 0)->get();
		return view('client.introduce', compact('table'));
	}

	// booktable
	public function postBookTable(Request $req){
		$table = Table::find($req->id);
		// transaction
		DB::beginTransaction();
		try {
			// save customer
			$customer = new Customer();
			$customer->name = $req->name;
			$customer->email = $req->email;
			$customer->phone = $req->phone;
			$customer->address = $table->table_name;
			$customer->note = $req->note;
			$customer->save();

			// save booktable
			$booktable = new BookTable();
			$booktable->note = $req->note;
			$booktable->id_customer = $customer->id;
			$booktable->id_table = $table->id;
			$booktable->status = 0;
			$booktable->save();

			//save table
			$table->status = 1;
			$table->save();

			DB::commit();
			return redirect('/introduce')->with('message', 'Đã đặt bàn, chúng tôi sẽ liên hệ lại với bạn sau ít phút');
		}
		catch (Exception $e) {
			DB::rollBack();
			return redirect('/')->with('error', 'Có Lỗi Xảy Ra, Vui Lòng Thử Lại!');
		}
	}

	public function getInfo(){
		return view('client.info');
	}

	public function postInfo(Request $req){
		$user = User::where('id',Auth::user()->id)->first();
		$user->name = $req->name;
		$user->phone = $req->phone;
	 	$user->address = $req->address;
	 	if($req->hasFile('avatar')){
			$file = $req->file('avatar');
			$duoi = $file->getClientOriginalExtension();
			if($duoi != 'jpg' && $duoi != 'png' && $duoi != 'jpeg')
			{
				return redirect()->back()->with('message', 'File ảnh không đúng định dạng');
			}
			$name = $file->getClientOriginalName();
			$image = str_random(5)."_".$name;
			while(file_exists("img/".$image))
			{   
				$image = str_random(5)."_".$name;
			}
			$file->move('img/', $image);    
			$user->avatar = $image;
		}
		else{
			$user->avatar = $user->avatar;
		}
		$user->save();
		return redirect()->back()->with('message', 'Đã Cập Nhật');
	}

	public function getChangePass(){
		return view('client.change-pass');
	}

	public function postChangePass(Request $req){
		$user = User::where('id', Auth::user()->id)->first();
		if(!Hash::check($req->oldPass, $user->password)){
			return redirect()->back()->with('error', 'Nhập Mật Khẩu Cũ không chính xác');
		}
		if($req->newPass != $req->rePass){
			return redirect()->back()->with('error', 'Mật Khẩu Mới Không Khớp');
		}
		$user->password = \Hash::make($req->newPass);
		$user->save();
		return redirect()->back()->with('message', 'Đã Đổi Mật Khẩu');
	}

	public function getHistoryOrder(){
		$customer = Customer::where('email',  Auth::user()->email)->select('id')->get();
		$order = Order::whereIn('id_customer', $customer)->get();
		return view('client.history', compact('order'));
	}

	public function getBill($id){
		$order = Order::where('id', $id)->first();
		$order_details = OrderDetails::where('id_order',$id)->get();
		return view('client.order.view', compact('order', 'order_details'));
	}
}

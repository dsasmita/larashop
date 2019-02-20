<?php

namespace App\Http\Controllers;

use App\City;
use App\Book;
use App\Http\Resources\Cities as CityResourceCollection;
use App\Http\Resources\Provinces as ProvinceResourceCollection;
use App\Province;
use Auth;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function provinces()
    {
        return new ProvinceResourceCollection(Province::get());
    }

    public function cities()
    {
        return new CityResourceCollection(City::get());
    }

    public function shipping(Request $request)
    {
        $user    = Auth::user();
        $status  = "error";
        $message = "";
        $data    = null;
        $code    = 200;
        if ($user) {
            $this->validate($request, [
                'name'        => 'required',
                'address'     => 'required',
                'phone'       => 'required',
                'province_id' => 'required',
                'city_id'     => 'required',
            ]);
            $user->name        = $request->name;
            $user->address     = $request->address;
            $user->phone       = $request->phone;
            $user->province_id = $request->province_id;
            $user->city_id     = $request->city_id;
            if ($user->save()) {
                $status  = "success";
                $message = "Update shipping success";
                $data    = $user->toArray();
            } else {
                $message = "Update shipping failed";
            }
        } else {
            $message = "User not found";
        }
        return response()->json([
            'status'  => $status,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    public function couriers()
    {
        $couriers = [
            ['id' => 'jne', 'text' => 'JNE'],
            ['id' => 'tiki', 'text' => 'TIKI'],
            ['id' => 'pos', 'text' => 'POS'],
        ];
        return response()->json([
            'status'  => 'success',
            'message' => 'couriers',
            'data'    => $couriers,
        ], 200);
    }

    public function services(Request $request)
    {
        $status  = "error";
        $message = "";
        $data    = [];
        // validasi kelengkapan data
        $this->validate($request, [
            'courier' => 'required',
            'carts'   => 'required',
        ]);
        $user = Auth::user();
        if ($user) {
            $destination = $user->city_id;
            if ($destination > 0) {
                // hardcode, silakan sesuaikan dengan asal pengiriman barangnya
                $origin  = 153; // Jakarta Selatan
                $courier = $request->courier;
                $carts   = $request->carts;
                $carts   = json_decode($carts, true); // transformasi dari json menjadi array
                // validasi data belanja
                $validCart          = $this->validateCart($carts); // panggil fungsi validateCart()
                $data['safe_carts'] = $validCart['safe_carts'];
                $data['total']      = $validCart['total'];
                $quantity_different = $data['total']['quantity_before']
                    != $data['total']['quantity'];
                $weight = $validCart['total']['weight'] * 1000;
                if ($weight > 0) {
                    // request courier service API RajaOngkir
                    $parameter = [
                        "origin"      => $origin,
                        "destination" => $destination,
                        "weight"      => $weight,
                        "courier"     => $courier,
                    ];

                    // check ongkos kirim ke api RajaOngkir melalui fungsi getServices()
                    $respon_services = $this->getServices($parameter);
                    if ($respon_services['error'] == null) {
                        $services = [];
                        $response = json_decode($respon_services['response']); // transformasi dari json menjadi array
                        $costs    = $response->rajaongkir->results[0]->costs;
                        foreach ($costs as $cost) {
                            // parsing ongkos kirimnya
                            $service_name       = $cost->service;
                            $service_cost       = $cost->cost[0]->value;
                            $service_estimation = str_replace('hari', '', trim($cost->cost[0]->etd));
                            $services[]         = [
                                'service'    => $service_name,
                                'cost'       => $service_cost,
                                'estimation' => $service_estimation,
                                'resume'     => $service_name . ' [ Rp.' . number_format($service_cost) . ', Etd: ' . $cost->cost[0]->etd . ' day(s) ]',
                            ];
                        }
                        // Response
                        if (count($services) > 0) {
                            $data['services'] = $services;
                            $status           = "success";
                            $message          = "getting services success";
                        } else {
                            $message = "courier services unavailable";
                        }
                        // ketika ternyata jumlah beli berbeda dengan jumlah stok maka tampilkanwarninng
                        if ($quantity_different) {
                            $status  = "warning";
                            $message = "Check cart data, " . $message;
                        }
                    } else {
                        $message = "cURL Error #:" . $respon_services['error'];
                    }
                } else {
                    $message = "weight invalid";
                }
            } else {
                $message = "destination not set";
            }
        } else {
            $message = "user not found";
        }
        return response()->json([
            'status'  => $status,
            'message' => $message,
            'data'    => $data,
        ], 200);
    }

    protected function validateCart($carts)
    {
        $safe_carts = []; // persiapkan variabel untuk menampung data cart yang aman
        $total      = [
            'quantity_before' => 0,
            'quantity'        => 0,
            'price'           => 0,
            'weight'          => 0,
        ];
        $idx = 0;
        // looping data state carts yang dikirim ke server untuk memastikan data valid
        foreach ($carts as $cart) {
            $id       = (int) $cart['id'];
            $quantity = (int) $cart['quantity'];
            $total['quantity_before'] += $quantity;
            $book = Book::find($id); // ambil data buku berdasarkan id-nya
            if ($book) {
                if ($book->stock > 0) {
                    // check real stock
                    $safe_carts[$idx]['id']     = $book->id;
                    $safe_carts[$idx]['title']  = $book->title;
                    $safe_carts[$idx]['cover']  = $book->cover;
                    $safe_carts[$idx]['price']  = $book->price;
                    $safe_carts[$idx]['weight'] = $book->weight;
                    if ($book->stock < $quantity) {
                        // jika jumlah yang dipesan melebihi stokbukumaka
                        $quantity = (int) $book->stock; // jumlah yang dipesan disamakan denganstok
                    }
                    $safe_carts[$idx]['quantity'] = $quantity;
                    $total['quantity'] += $quantity;
                    $total['price'] += $book->price * $quantity; //total price
                    $total['weight'] += $book->weight * $quantity;
                    $idx++;
                } else {
                    continue;
                }
            }
        }
        return [
            'safe_carts' => $safe_carts,
            'total'      => $total,
        ];
    }

    protected function getServices($data)
    {
        $url_cost = "https://api.rajaongkir.com/starter/cost";
        $key      = env('API_KEY_RO');
        $postdata = http_build_query($data);
        $curl     = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url_cost,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => "POST",
            CURLOPT_POSTFIELDS     => $postdata,
            CURLOPT_HTTPHEADER     => [
                "content-type: application/x-www-form-urlencoded",
                "key: " . $key,
            ],
        ]);
        $response = curl_exec($curl);
        $error    = curl_error($curl);
        curl_close($curl);
        return [
            'error'    => $error,
            'response' => $response,
        ];
    }

}

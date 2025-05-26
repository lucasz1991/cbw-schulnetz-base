<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


class CashRegisterController extends Controller
{
    public function registerSale(Request $request)
    {   
        if ($request->input('event') === 'stockitementry.create') {
            $payload = $request->input('payload');
            Log::info('payload:', ['payload' => $payload]);
                if (isset($payload['item'])) {
                    $itemNumber = $payload['item'];
                    $product = Product::where('cash_register_id', $itemNumber)->first();
                    if ($product) {
                        $salePrice = ($product->discount > 0) ? $product->discount_price : $product->price;

                        Sale::create([
                            'product_id' => $product->id,
                            'customer_id' => $product->customer_id, 
                            'rental_id' => $product->shelf_rental_id, 
                            'date' => Carbon::now(), 
                            'sale_price' => $salePrice,
                        ]); 
                        $product->update(['status' => 4]);
                        Log::info('Produktstatus aktualisiert:', [
                            'product_id' => $product->id,
                            'status' => $product->status,
                        ]);
                    } else {
                        Log::warning('Kein Produkt mit dieser Item-Nummer gefunden:', ['item' => $itemNumber]);
                    }
                    Log::warning('Item-Nummer fehlt im Payload:', ['payload' => $payload]);
                }
            } else {
                Log::info('Nicht das erwartete Event:', ['event' => $request->input('event')]);
            }
        return response()->json(['message' => 'Verkauf erfolgreich registriert.'], 200);
    }
}

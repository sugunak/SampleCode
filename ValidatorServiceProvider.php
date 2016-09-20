<?php

namespace App\Providers;

use App\Http\Requests;
use App\Order;
use App\OrderDynamicStatus;
use App\OrderItem;
use App\PurchaseOrder;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class ValidatorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        Validator::extend('alpha_space', function ($attribute, $value, $parameters) {
            if (preg_match('[\w]', $value)) {
                return true;
            } else {
                return false;
            }
        });

        Validator::extend('purchase_order_validate_quantity', function ($attribute, $value, $parameters) {
            $orderItemId = Input::get('order_item_id');
            $purchaseOrderId = Input::get('id');
            $totalQuantity = OrderItem::find($orderItemId)->quantity;
            if ($purchaseOrderId) {
                $assignedQuantity = PurchaseOrder::getpurchaseOrdersNotRejectedAndFailed()->where('order_item_id', $orderItemId)->where('id', '<>', $purchaseOrderId)->sum('quantity');
            } else {
                $assignedQuantity = PurchaseOrder::getpurchaseOrdersNotRejectedAndFailed()->where('order_item_id', $orderItemId)->sum('quantity');
            }
            $newQuantity = $assignedQuantity + Input::get('quantity');
            if ($totalQuantity >= $newQuantity) {
                return true;
            } else {
                return false;
            }
        });

        Validator::extend('check_with_total_amount', function ($attribute, $value, $parameters) {
            $orderId = Input::get('order_id');
            $order = Order::find($orderId);
            if ($value <= $order->balance_amount) {
                return true;
            } else {
                return false;
            }
        });

        Validator::extend('check_with_other_target_dates', function ($attribute, $value, $parameters) {

            preg_match_all('!\d+!', $attribute, $number);
            $i = $number[0][0];
            $j = $i - 1;
            if ($attribute != '') {
                while (Input::get('target_date_' . $j) == '' && $j > 1) {
                    $j--;
                }
                if (Input::get('target_date_' . $i) >= Input::get('target_date_' . $j)) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return true;
            }
        });

        Validator::extend('check_with_other_actual_completed_dates', function ($attribute, $value, $parameters) {

            preg_match_all('!\d+!', $attribute, $number);
            $i = $number[0][0];
            $j = $i - 1;
            if ($attribute != '') {
                while (Input::get('actual_date_of_completion_' . $j) == '' && $j > 1) {
                    $j--;
                }
                if (Input::get('actual_date_of_completion_' . $i) >= Input::get('actual_date_of_completion_' . $j)) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return true;
            }
        });

        Validator::extend('check_with_date', function ($attribute, $value, $parameters) {
            $date = $parameters[0];
            if (Input::get($attribute) >= $date) {
                return true;
            } else {
                return false;
            }
        });
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}

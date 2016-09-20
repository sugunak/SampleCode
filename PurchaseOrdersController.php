<?php

namespace App\Http\Controllers;

use App\Events\InspectionFailed;
use App\Events\PurchaseOrdersPlaced;
use App\Events\SupplierAdded;
use App\Events\SupplierUpdated;
use App\Http\Requests;
use App\Http\Requests\PurchaseOrderRequest;
use App\Order;
use App\OrderItem;
use App\PurchaseOrder;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;

class PurchaseOrdersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create($orderId, $orderItemId)
    {
        $order = Order::find($orderId);
        if ($order->order_status == "Approved by Supply Chain Executive" || $order->isProductionStarted()) {
            $order_item = OrderItem::find($orderItemId);
            $failedPurchaseOrderSuppliers = $this->getFailedPurchaseOrderSuppliers($order_item);
            $suppliers = User::getSuppliersExcluding($failedPurchaseOrderSuppliers);
            $orderItemName = $order_item->item->item_name;
            $total_quantity = $order_item->quantity;
            $remaining_quantity = $total_quantity - $order_item->purchaseOrders->sum(function ($purchaseOrder) {
                    if (!($purchaseOrder->isPurchaseOrderRejectedOrFailed()))
                        return $purchaseOrder->quantity;
                });
            if ($remaining_quantity != 0) {
                $orderNumber = Order::where('id', $orderId)->value('order_number');

                return view('order.items.purchaseOrder.create', compact('suppliers', 'remaining_quantity', 'total_quantity', 'orderId', 'orderItemId', 'orderNumber', 'orderItemName'));
            } else {
                return view('errors.no_permission_error')->with('message', 'You could not create purchase order before the approval of supply chain executive');
            }
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request $request
     * @return Response
     */
    public function store(PurchaseOrderRequest $request, $orderId, $itemId)
    {
        $input = $request->except('inspection_status');
        $order = Order::find($orderId);
        try {
            if ($order->isProductionStarted()) {
                $input['approve_reorder'] = 0;
            } else {
                $input['approve_reorder'] = 1;
            }
            $purchaseOrder = PurchaseOrder::create($input);

            $this->sendMailWhenPurchaseOrderPlaced($orderId);

            return redirect('orders/' . $orderId . '/items/' . $itemId)->with('message', 'New purchase order created successfully !!!');

        } catch (QueryException $e) {
            Log::error("Error while creating purchase order", [$e]);
            return redirect('orders/' . $orderId . '/items/' . $itemId)->with('message', 'Error while creating purchase order');
        }
    }

    public function sendMailWhenPurchaseOrderPlaced($orderId)
    {
        $order = Order::with('order_items')->find($orderId);
        $item_quantity = $order->order_items->sum('quantity');
        $purchase_order_quantity = $order->purchase_orders->sum('quantity');
        if (($item_quantity == $purchase_order_quantity)) {
            Event::fire(new PurchaseOrdersPlaced($orderId));
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return Response
     */
    public function show($orderId, $orderItemId, $purchaseOrderId)
    {
        $order = Order::find($orderId);
        $purchaseOrder = PurchaseOrder::with('inspection_reports')->where('id', $purchaseOrderId)->first();
        $orderNumber = Order::where('id', $orderId)->value('order_number');
        $orderItemName = OrderItem::find($orderItemId)->item->item_name;
        return view('order.items.purchaseOrder.show', compact('purchaseOrder', 'orderId', 'orderItemId', 'orderNumber', 'orderItemName', 'purchaseOrderId', 'order'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return Response
     */
    public function edit($orderId, $orderItemId, $purchaseOrderId)
    {
        $order = Order::find($orderId);
        $purchaseOrder = PurchaseOrder::find($purchaseOrderId);
        if ($order->display_status == "Approved by Supply Chain Executive" || ($order->isProductionStarted() && !($purchaseOrder->isPurchaseOrderRejectedOrFailed()))) {
            $order_item = OrderItem::find($orderItemId);
            $failedPurchaseOrderSuppliers = $this->getFailedPurchaseOrderSuppliers($order_item);
            $suppliers = User::getSuppliersExcluding($failedPurchaseOrderSuppliers);
            $orderItemName = $order_item->item->item_name;
            $total_quantity = $order_item->quantity;
            $remaining_quantity = $total_quantity - $order_item->purchaseOrders->sum(function ($purchaseOrder) {
                    if (!($purchaseOrder->isPurchaseOrderRejectedOrFailed()))
                        return $purchaseOrder->quantity;
                });
            return view('order.items.purchaseOrder.edit', compact('purchaseOrder', 'suppliers', 'orderItemId', 'orderItemName', 'total_quantity', 'remaining_quantity', 'order'));
        } else {
            return view('errors.no_permission_error')->with('message', 'You could not edit purchase order either it is approved by Managing Director or purchase order gets failed/rejected.');
        }

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request $request
     * @param  int $id
     * @return Response
     */
    public function update(PurchaseOrderRequest $request, $orderId, $itemId, $purchaseOrderId)
    {
        $order = Order::find($orderId);
        $user = Auth::user();
        $purchaseOrder = PurchaseOrder::with('supplier')->where('id', $purchaseOrderId)->first();
        if ($order->isProductionStarted()) {
            if ($purchaseOrder->approve_reorder == 1) {
                if (($user->is('supply.chain.executive') || $user->is('managing.director')) && $purchaseOrder->purchase_order_date == 0) {

                    $input = $request->only('purchase_order_number', 'purchase_order_date');
                    $validator = Validator::make($input, [
                        'purchase_order_number' => 'required|between:2,255|unique:purchase_orders,purchase_order_number',
                        'purchase_order_date' => 'required|date',
                    ]);
                    if ($validator->fails()) {
                        return redirect('orders/' . $orderId . '/items/' . $itemId . '/purchaseOrders/' . $purchaseOrderId . '/edit')
                            ->withErrors($validator)
                            ->withInput();
                    }
                } else {
                    $input = $request->only('inspection_status');
                }
            } else {
                return redirect('orders/' . $orderId . '/items/' . $itemId . '/purchaseOrders/' . $purchaseOrderId . '/edit')->with('message', 'This is a re-order, has to be approved by MD');
            }
        } else {
            $input = $request->only('supplier_id', 'quantity');
            $validator = Validator::make($input, [
                'quantity' => 'required|purchase_order_validate_quantity',
            ]);
            if ($validator->fails()) {
                return redirect('orders/' . $orderId . '/items/' . $itemId . '/purchaseOrders/' . $purchaseOrderId . '/edit')
                    ->withErrors($validator)
                    ->withInput();
            }
        }
        $purchaseOrder->fill($input);
        try {
            $purchaseOrder->save();

            if (!($order->isProductionStarted())) {
                $this->sendMailWhenPurchaseOrderPlaced($orderId);
            }
            if (isset($input['inspection_status']) && $input['inspection_status'] == "Fail")
                Event::fire(new InspectionFailed($purchaseOrderId));
            return redirect('orders/' . $orderId . '/items/' . $itemId)->with('message', 'Purchase order Details updated successfully !!!');
        } catch (QueryException $e) {
            Log::error("Error while updating purchase order", [$e]);
            return redirect('orders/' . $orderId . '/items/' . $itemId . '/purchaseOrders/' . $purchaseOrderId . '/edit')->with('message', 'Error while updating purchase order');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return Response
     */
    public function destroy($orderId, $orderItemId, $PurchaseOrderId)
    {
        PurchaseOrder::find($PurchaseOrderId)->delete();
        return redirect('orders/' . $orderId . '/items/' . $orderItemId);
    }

    public function purchaseOrderApprove($orderId, $orderItemId, $PurchaseOrderId)
    {
        $purchaseOrder = PurchaseOrder::find($PurchaseOrderId);
        $purchaseOrder->approve_reorder = 1;
        $purchaseOrder->save();
        return redirect('orders/' . $orderId . '/items/' . $orderItemId);
    }

    public function purchaseOrderReject($orderId, $orderItemId, $PurchaseOrderId)
    {
        $purchaseOrder = PurchaseOrder::find($PurchaseOrderId);
        $purchaseOrder->inspection_status = 'MD Rejected';
        $purchaseOrder->approve_reorder = 1;
        $purchaseOrder->save();
        return redirect('orders/' . $orderId . '/items/' . $orderItemId);
    }

    public function getFailedPurchaseOrderSuppliers($order_item)
    {
        return $order_item->purchaseOrders->filter(function ($purchase_order) {
            if ($purchase_order->isPurchaseOrderRejectedOrFailed())
                return $purchase_order;
        })->unique('supplier_id')->pluck('supplier_id')->toArray();
    }
}

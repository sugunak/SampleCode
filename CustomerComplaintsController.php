<?php

namespace App\Http\Controllers;

use App\CustomerComplaint;
use App\Events\ComplaintClosed;
use App\Events\ComplaintCreated;
use App\Http\Requests;
use App\Http\Requests\CustomerComplaintRequest;
use App\Order;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Request;

class CustomerComplaintsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $user = User::find(Auth::id());
        $orders = Order::where('customer_id', $user->id)->get();
        $orders = $orders->lists('order_number', 'id');

        if ($user->isCustomer()) {
            $complaints = CustomerComplaint::where('customer_id', $user->id)->orderBy('created_at', 'DESC')->get();
        } else {
            $complaints = CustomerComplaint::with('user')->orderBy('created_at', 'DESC')->get();
        }
        return view('customer.complaints.index', compact('complaints', 'orders'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request $request
     * @return Response
     */
    public function store(CustomerComplaintRequest $request)
    {
        $input = Request::all();
        $input['customer_id'] = Auth::id();
        $input['status'] = "Open";

        if (($input['order_item'] == 'order' && $input['order_id'] == '') || $input['order_item'] == 'general') {
            $input['order_id'] = NULL;
        }

        if (isset($input['complaint_image'])) {
            $complaintImage = $input['complaint_image'];
            $complaintImageFileName = $complaintImage->getClientOriginalName();
            $input['complaint_image'] = $complaintImageFileName;
            $complaint = CustomerComplaint::create($input);
            $filePath = '/complaints/' . $complaint->id . '/' . $complaintImageFileName;

            $s3 = \Storage::disk('s3')->getDriver();
            $s3->put($filePath, file_get_contents($complaintImage), array('ACL' => 'public-read'));
        } else {
            $complaint = CustomerComplaint::create($input);
        }

        Event::fire(new ComplaintCreated($complaint));

        return redirect('complaints/');
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return Response
     */
    public function show($id)
    {
        $complaints = CustomerComplaint::find($id);
        return view('customer.complaints.show', compact('complaints'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request $request
     * @param  int $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $complaint = CustomerComplaint::find($id);
        $complaint->status = "Closed";
        $complaint->closed_by_id = Auth::id();
        $complaint->save();

        Event::fire(new ComplaintClosed($complaint));

        return redirect('complaints/');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }
}

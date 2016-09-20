<?php

namespace App\Http\Controllers;

use App\CustomerComplaint;
use App\CustomerComplaintResponse;
use App\Events\ComplaintResponded;
use App\Http\Requests;
use App\Http\Requests\CustomerComplaintResponseRequest;
use DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Request;
use Log;

class CustomerComplaintResponsesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index($complaintId)
    {
        $complaint = CustomerComplaint::with('responses.user', 'user')->where('id', $complaintId)->first();
        return view('customer.complaints.show', compact('complaint'));
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
    public function store(CustomerComplaintResponseRequest $request, $complaintId)
    {
        $complaint_status = CustomerComplaint::find($complaintId)->status;
        if ($complaint_status == 'Open') {
            $input = Request::all();
            $input['complaint_id'] = $complaintId;
            $input['responded_by_id'] = Auth::id();

            if (isset($input['response_image'])) {
                $responseImage = $input['response_image'];
                $responseImageFileName = $responseImage->getClientOriginalName();
                $input['response_image'] = $responseImageFileName;
                $response = CustomerComplaintResponse::create($input);
                $filePath = '/complaints/' . $complaintId . '/response/' . $response->id . '/' . $responseImageFileName;

                $s3 = \Storage::disk('s3')->getDriver();
                $s3->put($filePath, file_get_contents($responseImage), array('ACL' => 'public-read'));
            } else {
                if ((isset($input['source_of_issue']) && $input['source_of_issue'] != '') || $input['others'] != '')
                    $response = CustomerComplaintResponse::create($input);
                else
                    return redirect('complaints/' . $complaintId . '/response')->with('message', 'You have to fill atleast one field');
            }

            Event::fire(new ComplaintResponded($response, $response->complaint));

            return redirect('complaints/' . $complaintId . '/response');
        } else {
            Log::error("Responded complaint is closed");
            return redirect('complaints/' . $complaintId . '/response');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return Response
     */
    public function show($id)
    {
        //
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
        //
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

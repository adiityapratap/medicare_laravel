<?php

namespace App\Http\Controllers\Backend;

use Log;
use App\Bus;
use App\BusZones;
use App\Http\Helpers\AppHelper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class BusController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $zone_list = AppHelper::getAppSettings('fee_trans_zones');
        $buses = Bus::select('id','name','numeric_value','order','status','note')->with('zones')->orderBy('order', 'asc')->get();

        // return $buses;
        return view('backend.academic.buses.list', compact('buses', 'zone_list'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //for get request
        $bus = NULL;
        $zone = NULL;
        $zone_list = AppHelper::getAppSettings('fee_trans_zones');

        return view('backend.academic.buses.add', compact('bus','zone', 'zone_list'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|min:2|max:255',
            'numeric_value' => 'required|integer',
            'order' => 'required|integer',
            'zone' => 'required',
            'note' => 'max:500',
        ]);

        $data = $request->all();
        $data['status'] = AppHelper::ACTIVE;

        $zones = $data['zone'];
        unset($data['zone']);
        unset($data['_token']);
			
        DB::beginTransaction();
        try {		
            $bid = Bus::insertGetId(
                $data
            );
            $zonedata = array();
            foreach($zones as $zone) {
                $zonedata[] = array(
                    'bus_id' => $bid,
                    'zone' => $zone
                );
            }
            
            BusZones::insert($zonedata);
            DB::commit();
        }
        catch(\Exception $e){
            DB::rollback();
            throw new \Exception($e->getMessage());
        }

        //now notify the admins about this record
        $msg = $data['name']." added by ".auth()->user()->name;
        $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
        // Notification end


        $msg = "Bus added.";

        return redirect()->route('bus.create')->with('success', $msg);
    }

    /**
     * bus status change
     * @return mixed
     */
    public function busStatus(Request $request, $id=0)
    {

        $bus =  Bus::findOrFail($id);
        if(!$bus){
            return [
                'success' => false,
                'message' => 'Record not found!'
            ];
        }

        $bus->status = (string)$request->get('status');

        $bus->save();

        return [
            'success' => true,
            'message' => 'Status updated.'
        ];

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Bus  $bus
     * @return \Illuminate\Http\Response
     */
    public function edit(Bus $bus)
    {
        $zone = [];
        foreach($bus->zones as $z) {
            $zone[] = $z->zone;
        }
        $zone_list = AppHelper::getAppSettings('fee_trans_zones');

        return view('backend.academic.buses.add', compact('bus','zone', 'zone_list'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Bus  $bus
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Bus $bus)
    {
        $this->validate($request, [
            'name' => 'required|min:2|max:255',
            'numeric_value' => 'required|integer',
            'order' => 'required|integer',
            'zone' => 'required|integer',
            'note' => 'max:500',
        ]);

        $data = $request->all();
        unset($data['numeric_value']);

        Bus::whereId($id)->update(
            $data
        );


        $msg = "Bus updated.";

        return redirect()->route('bus.edit')->with('success', $msg);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Bus  $bus
     * @return \Illuminate\Http\Response
     */
    public function destroy(Bus $bus)
    {
        try{
            $bus->delete();
    
            //now notify the admins about this record
            $msg = $bus->name." deleted by ".auth()->user()->name;
            $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
            // Notification end
            return [
                'success' => true,
                'message' => 'Record deleted!'
            ];
            // return redirect()->route('bus.index')->with('success', 'Record deleted!');
        }catch(\Exception $e){
            DB::rollback();
            Log::error($e);
            return [
                'success' => false,
                'message' => 'Something went wrong!'
            ];
            // throw new \Exception($e->getMessage());
        }
    }
}

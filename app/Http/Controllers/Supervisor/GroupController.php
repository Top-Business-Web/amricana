<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\GroupColor;
use App\Models\GroupCustomer;
use App\Models\GroupMovement;
use App\Models\Groups;
use App\Models\SupervisorActivity;
use App\Models\SupervisorLog;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
//
class GroupController extends Controller
{

    public function selectTourguide(Request $request)
    {
        if ($request->ajax()) {

            $activity = $request->activity_id;

            $output = '<option>Select Supervisor</option>';

            $tourguides = SupervisorActivity::where('activity_id', $activity)
                ->where('status', 'available')
                ->whereDate('date_time', Carbon::now()->format('Y-m-d'))
                ->get();

            if ($tourguides->count() > 0) {
                foreach ($tourguides as $tourguide)
                    $output .= '<option value="' . $tourguide->supervisor_id . '">' . $tourguide->supervisors->name . '</option>';
            }

            return response($output, 200);

        } // end if

    } // end of selectTourguide

    public function groupMove(Request $request)
    {
        $date_time = Carbon::now()->format('Y-m-d');
        $accept = 'waiting';
        $request->all();


        try {
            $group_movment = GroupMovement::where('group_id', $request->group_id)
                ->whereDate('created_at', '=', $date_time)
                ->where('status', 'in')
                ->first();
//            return $group_movment;
            if ($request->activity_id == $group_movment->activity_id) {
                return redirect()->back()->with('success', 'You Are Already In The Same Activity');
            } else {

                $outGroup = GroupMovement::where('group_id', $request->group_id)
                    ->whereDate('created_at', '=', $date_time)
                    ->update(['status' => 'out']);

                $inGroup = GroupMovement::create([
                    'date_time' => Carbon::now()->format('Y-m-d H:i:s'),
                    'group_id' => $request->group_id,
                    'activity_id' => $request->activity_id,
                    'supervisor_accept_id' => $request->supervisor_accept_id,
                    'accept' => $accept,
                    'status' => 'in',
                ]);

                if (!$inGroup) {
                    $outGroup = GroupMovement::where('group_id', $request->group_id)
                        ->whereDate('created_at', '=', $date_time)
                        ->update(['status' => 'in']);
                } else if ($inGroup) {
                    return redirect()->back()->with('success', 'Group moved successfully');
                } else {
                    return redirect()->back()->with('error', 'please fill data and try again');
                }

            }
        } catch (Exception $e) {

            return redirect()->back()->with('error', 'please fill data and try again');
        }

    } // end group_move

    public function groupMoveCreate(Request $request)
    {
        $date_time = Carbon::now()->format('Y-m-d');
        $accept = 'waiting';
        $request->all();
        $color = $request->color;
        $group = $request->group_id;


//        dd($request->all());
        try {

            $groupMovement = GroupMovement::create([
                'date_time' => Carbon::now()->format('Y-m-d H:i:s'),
                'group_id' => $request->group_id,
                'activity_id' => $request->activity_id,
                'supervisor_accept_id' => $request->supervisor_accept_id,
                'accept' => $accept,
                'status' => 'in',
            ]);

            if ($request->has('color')) {
                GroupColor::where('group_id', $group)
                    ->whereDate('created_at', '=', Carbon::now()->format('Y-m-d'))
                    ->update(['color' => $color]);

                GroupCustomer::where('group_id', $group)->update(['color' => $color]);
            }

            $GroupCustomer = GroupCustomer::where('group_id', $request->group_id)
                ->whereDate('created_at', '=', $date_time)
                ->update([
                    'status' => 'in'
                ]);

            if ($groupMovement && $GroupCustomer) {
                return redirect()->back()->with('success', 'Group moved successfully');
            } else {
                return redirect()->back()->with('error', 'error try again');
            }
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'please fill data and try again');
        } // end try & catch

    } // end move create

    public function joinGroup(Request $request)
    {
//        try {

            $groupFrom = $request->groupId;
            $groupTo = $request->groupJoin;

            $group = GroupCustomer::where('id', $groupFrom)
                ->whereDate('created_at', '=', Carbon::now()->format('Y-m-d'))
                ->first();

//            $movement = GroupMovement::where('group_id', $group->group_id)
//                ->whereDate('created_at', '=', Carbon::now()->format('Y-m-d'))
//                ->first();

            $group->update(['group_id' => $groupTo]);

//            if ($movement !== null) {
//                $movement->update(['status' => 'out']);
//            }

            if ($group) {
//                return redirect()->back()->with('success', 'Group joined successfully');
                return response()->json(['status' => 200]);
            } else {
//                return redirect()->back()->with('error', 'error try again !');
                return response()->json(['status'=> 405]);
            }
//        } catch (Exception $e) {
//            return redirect()->route('platform');
//        } // end try & catch

    } // end join group

    public function returnWaitingRoom(Request $request)
    {
        $group = $request->group;

        $groupMovement = GroupMovement::where('group_id', $group)
            ->delete();


        SupervisorActivity::where('supervisor_id',auth()->user()->id)
            ->where('status','not_available')
            ->update(['status' => 'available']);

        SupervisorLog::created([
            'name' => auth()->user()->name,
            'status' => 'available'
        ]);

        if ($groupMovement) {
            $groupColor = GroupColor::where('group_id', $group)
                ->update(['color' => null]);
            return response()->json(['status' => 200]);
        } else {
            return response()->json(['status' => 500]);
        }

    } // end returnGroupWaiting

    public function breakGroup(Request $request)
    {
        $inputs = $request->except('_token');
        $newGroup = Groups::where('status', '=', 'available')->first();
        $groupCustomer = GroupCustomer::where('id', $inputs['group_id'])
            ->where('sale_type', 'trip')
            ->whereDate('created_at', Carbon::now()->format('Y-m-d'))
            ->first();

        $arr = [
            'inputs' => $inputs,
            'old_group' => $groupCustomer,
            'new group' => $newGroup,
        ];

//            return $arr;
//            return $groupCustomer->quantity;

        if ($inputs['break_count'] < $groupCustomer->quantity) {


            $groupCustomer->quantity = $groupCustomer->quantity - $inputs['break_count'];
            $groupCustomer->save();

            GroupCustomer::create([
                'member_name' => $groupCustomer->member_name,
                'ticket_id' => $groupCustomer->ticket_id,
                'group_id' => $newGroup->id,
                'rev_id' => $groupCustomer->rev_id,
                'date_time' => Carbon::now()->format('Y-m-d H:i:s'),
                'quantity' => $inputs['break_count'],
                'sale_type' => $groupCustomer->sale_type,
                'status' => $groupCustomer->status,
            ]);

            GroupColor::create([
                'group_id' => $newGroup->id,
                'color' => null,
                'date_time' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            Groups::where('id', $newGroup->id)->update(['status' => 'not_available']);

            return redirect()->back()->with('error', 'group broked successfully');

        } else {
            return redirect()->back()->with('error', 'group can\'t break');
        }
    } // end break group

    public function newGroup()
    {
        $newGroup = Groups::where('status', '=', 'available')->first();

        if ($newGroup) {

            Groups::where('id', $newGroup->id)->update(['status' => 'not_available']);

            GroupCustomer::create([
                'member_name' => 'manual group',
                'ticket_id' => null,
                'group_id' => $newGroup->id,
                'rev_id' => null,
                'date_time' => Carbon::now()->format('Y-m-d H:i:s'),
                'quantity' => 0,
                'sale_type' => 'family',
                'status' => 'waiting',
            ]);

            GroupColor::create([
                'group_id' => $newGroup->id,
                'color' => null,
                'date_time' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            return redirect()->back()->with('success', 'group created successfully !');
        } else {
            return redirect()->back()->with('success', 'no group are available now');
        }

    } // end new group
}

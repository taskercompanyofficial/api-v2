<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffDocument;
use App\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StaffController extends Controller
{
    use QueryFilterTrait;

    public function index(Request $request)
    {
        $page = $request->input('page') ?? 1;
        $perPage = $request->input('perPage') ?? 10;
        $query = Staff::query()->with(['createdBy:id,name','updatedBy:id,name']);
        $this->applyJsonFilters($query, $request);
        $this->applySorting($query, $request);
        $this->applyUrlFilters($query, $request, ['first_name','last_name','slug','department','position','status','joining_date']);
        $staff = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json(['status'=>'success','data'=>$staff]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'=>'required|string|max:255',
            'middle_name'=>'nullable|string|max:255',
            'last_name'=>'required|string|max:255',
            'cnic'=>'required|string|max:20|unique:staff,cnic',
            'dob'=>'required|date',
            'email'=>'nullable|email',
            'phone'=>'nullable|string',
            'gender'=>'required|string|in:male,female,other',
            'profile_image'=>'required|string',
            'cnic_front_image'=>'required|string',
            'cnic_back_image'=>'required|string',
            'permanent_address'=>'nullable|string',
            'city'=>'nullable|string',
            'state'=>'nullable|string',
            'postal_code'=>'nullable|string',
            'designation'=>'nullable|string',
            'joining_date'=>'nullable|date',
            'notes'=>'nullable|string',
            'has_access_in_crm'=>'nullable|boolean|in:true,false',
            'crm_login_email'=>'nullable|email|unique:staff,crm_login_email',
            'password'=>'nullable|string',
        ]);

        $fullName = $validated['first_name'].' '.($validated['middle_name'] ?? '').' '.$validated['last_name'];
        $slug = Str::slug(trim($fullName));
        $original = $slug; $i=1;
        // hash the password
        if(isset($validated['password'])){
            $validated['crm_login_password'] = bcrypt($validated['password']);
        }
        while(Staff::where('slug',$slug)->exists()){ $slug=$original.'-'.$i; $i++; }
        $code = 'STF-'.date('Y').'-'.Str::upper(Str::random(6));
        $user = $request->user();

        $staff = Staff::create([
            ...$validated,
            'slug'=>$slug,
            'original_slug'=>$original,
            'code'=>$code,
            'created_by'=>$user->id,
            'updated_by'=>$user->id,
        ]);

        return response()->json(['status'=>'success','message'=>'Staff created successfully','slug'=>$staff->slug]);
    }

    public function show(string $slug)
    {
        $staff = Staff::with(['documents','createdBy:id,name','updatedBy:id,name'])->where('slug',$slug)->first();
        if(!$staff){ return response()->json(['status'=>'error','message'=>'Staff not found'],404); }
        return response()->json(['status'=>'success','data'=>$staff]);
    }

    public function update(Request $request, string $slug)
    {
        $staff = Staff::where('slug',$slug)->first();
        if(!$staff){ return response()->json(['status'=>'error','message'=>'Staff not found'],404); }

        $validated = $request->validate([
            'first_name'=>'sometimes|required|string|max:255',
            'middle_name'=>'nullable|string|max:255',
            'last_name'=>'sometimes|required|string|max:255',
            'cnic'=>'sometimes|required|string|max:20|unique:staff,cnic,'.$staff->id,
            'dob'=>'nullable|date',
            'email'=>'nullable|email',
            'phone'=>'nullable|string',
            'emergency_contacts'=>'nullable|array',
            'current_address'=>'nullable|string',
            'permanent_address'=>'nullable|string',
            'city'=>'nullable|string',
            'state'=>'nullable|string',
            'country'=>'nullable|string',
            'postal_code'=>'nullable|string',
            'latitude'=>'nullable|string',
            'longitude'=>'nullable|string',
            'position'=>'nullable|string',
            'department'=>'nullable|string',
            'employment_type'=>'nullable|string|in:full_time,part_time,contract',
            'joining_date'=>'nullable|date',
            'status'=>'nullable|string|in:active,inactive,terminated,on_leave',
            'tags'=>'nullable|array',
            'has_access_in_crm'=>'nullable|boolean|in:true,false',
            'crm_login_email'=>'nullable|email|unique:staff,crm_login_email,'.$staff->id,
            'password'=>'nullable|string',
        ]);
        if(isset($validated['password'])){
            $validated['crm_login_password'] = bcrypt($validated['password']);
        }
        if(isset($validated['first_name']) || isset($validated['last_name'])){
            $fullName = ($validated['first_name'] ?? $staff->first_name).' '.($validated['middle_name'] ?? $staff->middle_name).' '.($validated['last_name'] ?? $staff->last_name);
            $newSlug = Str::slug(trim($fullName));
            $original=$newSlug; $i=1;
            while(Staff::where('slug',$newSlug)->where('id','!=',$staff->id)->exists()){ $newSlug=$original.'-'.$i; $i++; }
            $validated['slug']=$newSlug;
        }

        $user = $request->user();
        $validated['updated_by']=$user->id;
        $staff->update($validated);
        return response()->json(['status'=>'success','message'=>'Staff updated successfully','slug'=>$staff->slug]);
    }

    public function destroy(string $slug)
    {
        $staff = Staff::where('slug',$slug)->first();
        if(!$staff){ return response()->json(['status'=>'error','message'=>'Staff not found'],404); }
        $staff->delete();
        return response()->json(['status'=>'success','message'=>'Staff deleted successfully']);
    }
}
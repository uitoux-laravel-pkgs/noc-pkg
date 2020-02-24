<?php

namespace Abs\NocPkg;
use Abs\Basic\Attachment;
use Abs\NocPkg\Noc;
use Abs\NocPkg\NocType;
use App\Config;
use App\Http\Controllers\Controller;
use Auth;
use Carbon\Carbon;
use DB;
use File;
use Illuminate\Http\Request;
use Storage;
use PDF;
use Validator;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\Response;


class NocController extends Controller {

	private $company_id;
	public function __construct() {
		$this->data['theme'] = config('custom.admin_theme');
		$this->company_id = config('custom.company_id');
	}

	public function getNocs(Request $request) {
		$this->data['nocs'] = Noc::
			select([
			'nocs.question',
			'nocs.answer',
		])
			->where('nocs.company_id', $this->company_id)
			->orderby('nocs.display_order', 'asc')
			->get()
		;
		$this->data['success'] = true;

		return response()->json($this->data);

	}

	public function getFilterData(){
		$this->data['noc_type_list'] = NocType::select('name','id')->get();
		$this->data['noc_status_list'] = Config::where('entity_type_id',27)->select('name','id')->get();
		return response()->json($this->data);
	}

	public function getNocList(Request $request) {
		//dd($this->data['theme']);
		$this->company_id = Auth::user()->company_id;
		$nocs = Noc::withTrashed()
			->join('noc_types','nocs.type_id','=','noc_types.id')
			->join('configs','nocs.status_id','=','configs.id')
			->join('asps','nocs.to_entity_id','asps.id')
			->select([
				'nocs.id as id',
				'nocs.number as number',
				'noc_types.name as noc_type_name',
				'configs.name as status_name',
				'asps.name as asp_name',
				//DB::raw('DATE_FORMAT(nocs.created_at,"%d-%m-%Y") as created_at'),
				DB::raw('DATE_FORMAT(nocs.created_at,"%d-%m-%Y %H:%i:%s") as create_date'),
				DB::raw('IF(nocs.deleted_at IS NULL, "Active","Inactive") as status')
			]);
			if($request->noc_type_id){
				$nocs = $nocs->where('noc_types.id',$request->type_id);
			}
			if($request->noc_status_id){
				$nocs = $nocs->where('configs.id',$request->noc_status_id);
			}
			if($request->asp_code){
				$nocs = $nocs->where('asps.asp_code', 'like', $request->asp_code);
			}
			if($request->noc_number){
				$nocs = $nocs->where('nocs.number', 'like', $request->noc_number);
			}
			/*->where('nocs.company_id', $this->company_id)
			->where('nocs.for_id', $request->type_id)*/
		/*->where(function ($query) use ($request) {
				if (!empty($request->question)) {
					$query->where('nocs.question', 'LIKE', '%' . $request->question . '%');
				}
			})*/
			$nocs =$nocs->orderby('nocs.id', 'desc');
			//dd($nocs->first());
		return Datatables::of($nocs)
			->addColumn('status_name', function ($nocs) {
				$color_part = $nocs->status_name=='Completed' ? '':'color-warning';
				return '<span class="status-info '.$color_part.'"></span>'.$nocs->status_name;
			})
			->addColumn('action', function ($nocs) {

				$output = '<div class="dataTable-actions wid-100">
				<a href="#!/noc-pkg/noc/view/' . $nocs->id . '">
					                <i class="fa fa-eye dataTable-icon--view" aria-hidden="true"></i>
					            </a>';
				//if (Entrust::can('delete-activities')) {
					$output.= '<a onclick="angular.element(this).scope().deleteConfirm(' . $nocs->id . ')" href="javascript:void(0)">
						                <i class="fa fa-trash dataTable-icon--trash cl-delete" data-cl-id =' . $nocs->id . ' aria-hidden="true"></i>
						            </a>';

				//}
				/*$img1 = asset('public/themes/' . $this->data['theme'] . '/img/content/table/edit-yellow.svg');
				$img1_active = asset('public/themes/' . $this->data['theme'] . '/img/content/table/edit-yellow-active.svg');
				$img_delete = asset('public/themes/' . $this->data['theme'] . '/img/content/table/delete-default.svg');
				$img_delete_active = asset('public/themes/' . $this->data['theme'] . '/img/content/table/delete-active.svg');
				$output = '';*/
				/*$output .= '<a href="#!/noc-pkg/noc/view/' . $nocs->id . '" id = "" ><img src="' . $img1 . '" alt="Edit" class="img-responsive" onmouseover=this.src="' . $img1_active . '" onmouseout=this.src="' . $img1 . '"></a>
					<a href="javascript:;" data-toggle="modal" data-target="#noc-delete-modal" onclick="angular.element(this).scope().deleteNoc(' . $nocs->id . ')" title="Delete"><img src="' . $img_delete . '" alt="Delete" class="img-responsive delete" onmouseover=this.src="' . $img_delete_active . '" onmouseout=this.src="' . $img_delete . '"></a>
					';*/
				return $output;
			})
			->make(true);
	}

	public function getNocFormData(Request $r) {
		$id = $r->id;
		if (!$id) {
			$noc = new Noc;
			$attachment = new Attachment;
			$action = 'Add';
		} else {
			$noc = Noc::withTrashed()->find($id);
			$attachment = Attachment::where('id', $noc->logo_id)->first();
			$action = 'Edit';
		}
		$this->data['noc'] = $noc;
		$this->data['attachment'] = $attachment;
		$this->data['action'] = $action;
		$this->data['theme'];

		return response()->json($this->data);
	}
	public static function nocData($noc_id){
		$noc = Noc::withTrashed()->join('noc_types','nocs.type_id','=','noc_types.id')
			->join('configs','nocs.status_id','=','configs.id')
			->join('fy_quarters','nocs.for_id','=','fy_quarters.id')
			->join('asps','nocs.to_entity_id','asps.id')
			->select([
				'nocs.id as id',
				'nocs.type_id as type_id',
				'nocs.for_id as for_id',
				'nocs.status_id as status_id',
				'nocs.id as id',
				'nocs.number as number',
				'fy_quarters.name as quarter_name',
				'noc_types.name as noc_type_name',
				'configs.name as status_name',
				DB::raw('DATE_FORMAT(fy_quarters.date,"%d-%m-%Y") as start_date'),
				DB::raw('DATE_FORMAT(DATE_ADD(fy_quarters.date, INTERVAL +3 MONTH),"%d-%m-%Y") as end_date'),
				DB::raw('DATE_FORMAT(NOW(),"%d-%m-%Y") as cur_date'),
				DB::raw('DATE_FORMAT(nocs.created_at,"%d-%m-%Y %H:%i:%s") as create_date'),
				//DB::raw('date(d-m-Y) as current_date'),
				DB::raw('IF(nocs.deleted_at IS NULL, "Active","Inactive") as status'),
				'asps.workshop_name as workshop_name',
				DB::raw('CONCAT(asps.address_line_1,",",asps.address_line_2,",") as workshop_address'),
				'asps.contact_number1 as contact_number',
				'asps.name as asp_name'

			])->find($noc_id);
		
		//$noc['current_date'] = date('d-m-Y');
		//dd($noc);
		return $noc;
	}
	public function getNocViewData($noc_id) {
		//dd($r);
		$this->data['noc'] = $noc = $this->nocData($noc_id);
		if(!$noc){
			return response()->json(['success' => false,'errors' => ['NOC not found!!']]);
		}
		//dd($noc);
		//$this->data['attachment'] = $attachment;
		//$this->data['action'] = $action;
		/*if(){

		}*/
		$this->data['noc']['pdf_url'] ='#';
		$this->data['noc']['pdf_download'] = false;
		if($noc->status_id==401){
			$this->data['noc']['pdf_url'] = 'storage/app/public/noc/'.$noc_id.'.pdf';
			$this->data['noc']['pdf_download'] = true;
		}
		$this->data['success'] = true;
		$this->data['theme'];

		return response()->json($this->data);
	}

	/*public function downloadNocPdf($noc_id){
		$this->data['noc'] = $noc= $this->nocData($noc_id);
		if(!$noc){
			return response()->json(['success' => false,'errors' => ['NOC not found!!']]);
		}
		//dd($this->data['noc']);
		
		$filepath = 'storage/app/public/noc/' . $noc->id . '.pdf';
		$response = Response::download($filepath);
		ob_end_clean();
		return $response;
	}*/

	public function sendOTP($noc_id){
		$noc= Noc::join('asps','nocs.to_entity_id','asps.id')
			->select(
				'nocs.id as id',
				'asps.contact_number1 as contact_number',
				'nocs.otp as otp'
			)
			->where('nocs.id',$noc_id)
			->first();
		if($noc->contact_number){
			if($noc->otp){
				$otp =sendSMS2('OTP_FOR_ISSUE_NOC', 9944544521/*$noc->contact_number*/, $noc->otp);
			}else{
				$otp =generateOtpNoc(9944544521/*$noc->contact_number*/);
				$noc->otp = $otp;
				$noc->save();
			}
			$this->data['noc'] = $noc;
			$this->data['success'] = true;
			return response()->json($this->data);
		}else{
			return response()->json(['success' => false,'errors' => ['Invalid Contact Number']]);
		}
	}

	public function validateOTP(Request $request){
		//dd($request->all());
		$noc= Noc::find($request->noc_id);
		if($noc){
			if($noc->otp == $request->otp){
				$noc->status_id = 401;
				$noc->otp = NULL;
				//$noc->save();
				$this->data['success'] = true;
				$this->data['noc_id'] = $noc->id;
				$this->data['type_id'] = $noc->type_id;
				$this->data['noc'] = $noc= $this->nocData($noc->id);
				if (!Storage::disk('public')->has('noc/')) {
					Storage::disk('public')->makeDirectory('noc/');
				}
				$noc_path = storage_path('app/public/noc/');
				Storage::makeDirectory($noc_path, 0777);
				$pdf = PDF::loadView('pdf.noduec_pdf',$this->data['noc'])
					->setPaper('a4', 'landscape');
				$pdf->save(storage_path('app/public/noc/'.$noc->id.'.pdf'));
				return response()->json($this->data);

			}else{
				return response()->json(['success' => false, 'errors' => ['Invalid OTP,Please Re-Enter OTP..']]);
			}
		}else{
				return response()->json(['success' => false, 'errors' => ['NOC not found!!']]);
		}
	}


	public function saveNoc(Request $request) {
		//dd($request->all());
		try {
			$error_messages = [
				'name.required' => 'Name is Required',
				'name.unique' => 'Name is already taken',
				'delivery_time.required' => 'Delivery Time is Required',
				'charge.required' => 'Charge is Required',
			];
			$validator = Validator::make($request->all(), [
				'name' => [
					'required:true',
					'unique:nocs,name,' . $request->id . ',id,company_id,' . Auth::user()->company_id,
				],
				'delivery_time' => 'required',
				'charge' => 'required',
				'logo_id' => 'mimes:jpeg,jpg,png,gif,ico,bmp,svg|nullable|max:10000',
			], $error_messages);
			if ($validator->fails()) {
				return response()->json(['success' => false, 'errors' => $validator->errors()->all()]);
			}

			DB::beginTransaction();
			if (!$request->id) {
				$noc = new Noc;
				$noc->created_by_id = Auth::user()->id;
				$noc->created_at = Carbon::now();
				$noc->updated_at = NULL;
			} else {
				$noc = Noc::withTrashed()->find($request->id);
				$noc->updated_by_id = Auth::user()->id;
				$noc->updated_at = Carbon::now();
			}
			$noc->fill($request->all());
			$noc->company_id = Auth::user()->company_id;
			if ($request->status == 'Inactive') {
				$noc->deleted_at = Carbon::now();
				$noc->deleted_by_id = Auth::user()->id;
			} else {
				$noc->deleted_by_id = NULL;
				$noc->deleted_at = NULL;
			}
			$noc->save();

			if (!empty($request->logo_id)) {
				if (!File::exists(public_path() . '/themes/' . config('custom.admin_theme') . '/img/noc_logo')) {
					File::makeDirectory(public_path() . '/themes/' . config('custom.admin_theme') . '/img/noc_logo', 0777, true);
				}

				$attacement = $request->logo_id;
				$remove_previous_attachment = Attachment::where([
					'entity_id' => $request->id,
					'attachment_of_id' => 20,
				])->first();
				if (!empty($remove_previous_attachment)) {
					$remove = $remove_previous_attachment->forceDelete();
					$img_path = public_path() . '/themes/' . config('custom.admin_theme') . '/img/noc_logo/' . $remove_previous_attachment->name;
					if (File::exists($img_path)) {
						File::delete($img_path);
					}
				}
				$random_file_name = $noc->id . '_noc_file_' . rand(0, 1000) . '.';
				$extension = $attacement->getClientOriginalExtension();
				$attacement->move(public_path() . '/themes/' . config('custom.admin_theme') . '/img/noc_logo', $random_file_name . $extension);

				$attachment = new Attachment;
				$attachment->company_id = Auth::user()->company_id;
				$attachment->attachment_of_id = 20; //User
				$attachment->attachment_type_id = 40; //Primary
				$attachment->entity_id = $noc->id;
				$attachment->name = $random_file_name . $extension;
				$attachment->save();
				$noc->logo_id = $attachment->id;
				$noc->save();
			}

			DB::commit();
			if (!($request->id)) {
				return response()->json([
					'success' => true,
					'message' => 'NOC Added Successfully',
				]);
			} else {
				return response()->json([
					'success' => true,
					'message' => 'NOC Updated Successfully',
				]);
			}
		} catch (Exceprion $e) {
			DB::rollBack();
			return response()->json([
				'success' => false,
				'error' => $e->getMessage(),
			]);
		}
	}

	public function deleteNoc(Request $request) {
		DB::beginTransaction();
		try {
			$noc = Noc::withTrashed()->where('id', $request->id)->first();
			if (!is_null($noc->logo_id)) {
				Attachment::where('company_id', Auth::user()->company_id)->where('attachment_of_id', 20)->where('entity_id', $request->id)->forceDelete();
			}
			Noc::withTrashed()->where('id', $request->id)->forceDelete();

			DB::commit();
			return response()->json(['success' => true, 'message' => 'NOC Deleted Successfully']);
		} catch (Exception $e) {
			DB::rollBack();
			return response()->json(['success' => false, 'errors' => ['Exception Error' => $e->getMessage()]]);
		}
	}
}

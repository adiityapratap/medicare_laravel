@extends('frontend.layouts.master')
@section('pageTitle') @lang('site.menu_pre_admission') @endsection

@section('pageBreadCrumb')
	<!-- page title -->
	<div class="page-title">
		<div class="grid-row">
			<h1>@lang('site.menu_pre_admission')</h1>
			<nav class="bread-crumb">
				<a href="{{URL::route('home')}}">@lang('site.menu_home')</a>
				<i class="fa fa-long-arrow-right"></i>
				<a href="#">@lang('site.menu_pre_admission')</a>
			</nav>
		</div>
	</div>
	<!-- / page title -->
@endsection

@section('pageContent')
	<!-- content -->
	<div class="page-content">
		<!-- pre admission form section -->
		<div class="grid-row clear-fix">
			<div class="grid-col-row">
				<div class="grid-col grid-col-12 adm-form">
					<section>
						<!-- <h2>@lang('site.pre_admission_form_title')</h2> -->
						<div class="widget-contact-form">

							@if(session()->has('success'))
							    <div class="alert alert-success">
									<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
							        {{ session()->get('success') }}
							    </div>
							@endif
							@if(session()->has('error'))
							    <div class="alert alert-danger">
									<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
							        {{ session()->get('error') }}
							    </div>
							@endif

							<form id="initialForm" action="{{URL::route('site.preAdmissionForm')}}" method="POST" enctype="multipart/form-data" class="alt clear-fix">
								{{ csrf_field() }}
								<input type="hidden" name="student_id" id="student_id" class="other-fields">
								<div class="initial-fields-div pre-fields-div">
									@foreach($initialFields as $i => $field)
										<div style="position: relative;" class="grid-col-4">
											<label>{{ $field->field_title }}</label>
											@if($field->field_name == 'dob')
												<input type='text' readonly class="form-control date_picker2 initial-fields" id="{{ $field->field_name }}" name="{{ $field->field_name }}" placeholder="{{ $field->field_title }}" minlength="10" maxlength="255" {{ ($field->mandatory == '1') ? 'required' : '' }} />
											@else
												<input type="text" id="{{ $field->field_name }}" name="{{ $field->field_name }}" placeholder="{{ $field->field_title }}" class="initial-fields" {{ ($field->mandatory == '1') ? 'required' : '' }}>
											@endif
										</div>
									@endforeach
									<div class="pre-form-btn">
										<button id="next" type="button" class="cws-button border-radius alt">@lang('site.next')</button>
									</div>
								</div>
								<div class="post-fields-div pre-fields-div" style="display: none;">
									@foreach($otherFields as $i => $field)
										<div class="grid-col-4">
											<label>{{ $field->field_title }}</label>
											@if($field->field_name == 'class_id')
												{!! Form::select($field->field_name, $classes, '' , ['id' => $field->field_name, 'placeholder' => 'Pick a class...', 'class' => 'form-control other-fields', 'required' => ($field->mandatory == '1') ? 'true' : 'false']) !!}
											@elseif($field->field_name == 'gender')
												{!! Form::select($field->field_name, AppHelper::GENDER, '' , ['id' => $field->field_name, 'class' => 'form-control other-fields', 'required' => ($field->mandatory == '1') ? 'true' : 'false']) !!}
											@elseif($field->field_name == 'religion')
												{!! Form::select($field->field_name, AppHelper::RELIGION, '' , ['id' => $field->field_name, 'class' => 'form-control other-fields', 'required' => ($field->mandatory == '1') ? 'true' : 'false']) !!}
											@elseif($field->field_name == 'blood_group')
												{!! Form::select($field->field_name, AppHelper::BLOOD_GROUP, '' , ['id' => $field->field_name, 'class' => 'form-control other-fields', 'required' => ($field->mandatory == '1') ? 'true' : 'false']) !!}
											@elseif($field->field_name == 'nationality')
												{!! Form::select($field->field_name, ['Indian' => 'Indian', 'Other' => 'Other'], '' , ['id' => $field->field_name, 'class' => 'form-control other-fields', 'required' => ($field->mandatory == '1') ? 'true' : 'false']) !!}
											@elseif($field->field_name == 'need_transport')
												{!! Form::select($field->field_name, AppHelper::NEED_TRANSPORT, '' , ['id' => $field->field_name, 'class' => 'form-control other-fields', 'required' => ($field->mandatory == '1') ? 'true' : 'false']) !!}
											@elseif($field->field_name == 'transport_zone')
												{!! Form::select($field->field_name, ['' => '---Select Zone----'] + AppHelper::getAppSettings('fee_trans_zones'), '' , ['id' => $field->field_name, 'class' => 'form-control other-fields', 'required' => ($field->mandatory == '1') ? 'true' : 'false']) !!}
											@elseif($field->field_name == 'photo')
												<input type="file" class="form-control other-fields" accept=".jpeg, .jpg, .png" id="{{ $field->field_name }}" name="{{ $field->field_name }}" placeholder="Photo image" {{ ($field->mandatory == '1') ? 'required' : '' }}>
											@elseif(in_array($field->field_name, array('extra_activity', 'note', 'present_address', 'permanent_address')))
												<textarea id="{{ $field->field_name }}" name="{{ $field->field_name }}" placeholder="{{ $field->field_title }}" class="form-control other-fields" maxlength="255" {{ ($field->mandatory == '1') ? 'required' : '' }}></textarea>
											@else
												<input type="text" id="{{ $field->field_name }}" name="{{ $field->field_name }}" placeholder="{{ $field->field_title }}" class="other-fields" {{ ($field->mandatory == '1') ? 'required' : '' }}>
											@endif
										</div>
									@endforeach
									<div class="pre-form-btn">
										<button id="back" type="button" class="cws-button border-radius alt">@lang('site.back')</button>
										<button type="submit" class="cws-button border-radius alt">@lang('site.submit')</button>
									</div>
								</div>
							</form>
						</div>
					</section>
				</div>
			</div>
		</div>
		<!-- / contact form section -->
	</div>
	<!-- / content -->
@endsection

@section('extraScript')
<script type="text/javascript">
	// $('.select2').select2();
	window.getStudentDetails = '{{URL::Route("site.getStudentDetails")}}';
	$('#next').click(function() {
		if($('.initial-fields').valid()) {
			$.ajax({
	            type: 'GET',
	            url: window.getStudentDetails,
	            data: $('.initial-fields').serialize(),
	            success:function(resp) {
	            	$('.initial-fields-div').hide();
	            	$('.post-fields-div').show();
	            	if(resp.status) {
	                	$.each(resp.formData, function( index, value ) {
	                		$('#' + index).val(value);
						});
	                } else {
	                	var elements = document.querySelectorAll('.other-fields');
					    elements.forEach(function(element){
					        element.value = '';
					    });
	                }
	            }
	        });
		}
	});
	$('#back').click(function() {
		$('.initial-fields-div').show();
	    $('.post-fields-div').hide();
	});
	$(".date_picker2").datetimepicker({
        format: "DD/MM/YYYY",
        viewMode: 'years',
        ignoreReadonly: true
    });
</script>
@endsection
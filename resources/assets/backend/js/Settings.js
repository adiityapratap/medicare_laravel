export default class Settings {
    /**
     * app settings related codes
     */
    static instituteInit() {
        Generic.initCommonPageJS();
        $('select[name="fee_payment_notification"]').on('change', function () {
            let value = $(this).val();
            console.log(value);

            if(value==0){
                $('#divSmsGateWayList_PM').addClass('hide');
                $('#divTemplateList_PM').addClass('hide');
                $('#divTemplateList_PM').empty();
                $('#divSmsGateWayList_PM').empty();
            }
            if(value==1){
                Settings.setSmsGateWay("_PM");
                Settings.setTemplate("_PM");



            }

            if(value==2){
                Settings.setTemplate("_PM");
                $('#divSmsGateWayList_PM').addClass('hide');
                $('#divSmsGateWayList_PM').empty();
            }

            //fixed dom issue
            $('.select2-container').css('width','100%');
        });
        $('select[name="student_attendance_notification"]').on('change', function () {
            let value = $(this).val();
            // console.log(value);

            if(value==0){
                $('#divSmsGateWayList_St').addClass('hide');
                $('#divTemplateList_St').addClass('hide');
                $('#divTemplateList_St').empty();
                $('#divSmsGateWayList_St').empty();
            }
            if(value==1){
                Settings.setSmsGateWay("_St");
                Settings.setTemplate("_St");



            }

            if(value==2){
                Settings.setTemplate("_St");
                $('#divSmsGateWayList_St').addClass('hide');
                $('#divSmsGateWayList_St').empty();
            }

            //fixed dom issue
            $('.select2-container').css('width','100%');
        });
        $('select[name="employee_attendance_notification"]').on('change', function () {
            let value = $(this).val();
            // console.log(value);

            if(value==0){
                $('#divSmsGateWayList_Emp').addClass('hide');
                $('#divTemplateList_Emp').addClass('hide');
                $('#divTemplateList_Emp').empty();
                $('#divSmsGateWayList_Emp').empty();
            }
            if(value==1){
                Settings.setSmsGateWay("_Emp");
                Settings.setTemplate("_Emp");
                //it its update form then select item
                $('select[name="sms_gateway_Emp"]').val(window.gatewayEmp);
                $('select[name="notification_template_Emp"]').val(window.templateEmp);
            }

            if(value==2){
                Settings.setTemplate("_Emp");
                //it its update form then select item
                $('select[name="notification_template_Emp"]').val(window.templateEmp);
                $('#divSmsGateWayList_Emp').addClass('hide');
                $('#divSmsGateWayList_Emp').empty();
            }

            //fixed dom issue
            $('.select2-container').css('width','100%');
        });

        $('select[name="message_center_notification"]').on('change', function () {
            let value = $(this).val();

            if(value==0){
                $('#divSmsGateWayList_MC').addClass('hide');
                $('#divSmsGateWayList_MC').empty();
            }
            if(value==1){
                Settings.setSmsGateWay("_MC");
            }

            if(value==2){
                $('#divSmsGateWayList_MC').addClass('hide');
                $('#divSmsGateWayList_MC').empty();
            }

            //fixed dom issue
            $('.select2-container').css('width','100%');
        });


        $('select[name="circular_notification"]').on('change', function () {
            let value = $(this).val();

            if(value==0){
                $('#divSmsGateWayList_CC').addClass('hide');
                $('#divSmsGateWayList_CC').empty();
            }
            if(value==1){
                Settings.setSmsGateWay("_CC");
            }

            if(value==2){
                $('#divSmsGateWayList_CC').addClass('hide');
                $('#divSmsGateWayList_CC').empty();
            }

            //fixed dom issue
            $('.select2-container').css('width','100%');
        });


        $('select[name="homework_notification"]').on('change', function () {
            let value = $(this).val();

            if(value==0){
                $('#divSmsGateWayList_HW').addClass('hide');
                $('#divSmsGateWayList_HW').empty();
            }
            if(value==1){
                Settings.setSmsGateWay("_HW");
            }

            if(value==2){
                $('#divSmsGateWayList_HW').addClass('hide');
                $('#divSmsGateWayList_HW').empty();
            }

            //fixed dom issue
            $('.select2-container').css('width','100%');
        });

        $('select[name="pre_admission_interview_notification"]').on('change', function () {
            let value = $(this).val();
            console.log(value);

            if(value==0){
                $('#divSmsGateWayList_PA').addClass('hide');
                $('#divTemplateList_PA').addClass('hide');
                $('#divTemplateList_PA').empty();
                $('#divSmsGateWayList_PA').empty();
            }
            if(value==1){
                Settings.setSmsGateWay("_PA");
                Settings.setTemplate("_PA");



            }

            if(value==2){
                Settings.setTemplate("_PA");
                $('#divSmsGateWayList_PA').addClass('hide');
                $('#divSmsGateWayList_PA').empty();
            }

            //fixed dom issue
            $('.select2-container').css('width','100%');
        });

        $('select[name="promote_students_notification"]').on('change', function () {
            let value = $(this).val();

            if(value==0){
                $('#divSmsGateWayList_SP').addClass('hide');
                $('#divTemplateList_SP').addClass('hide');
                $('#divTemplateList_SP').empty();
                $('#divSmsGateWayList_SP').empty();
            }
            if(value==1){
                Settings.setSmsGateWay("_SP");
                Settings.setTemplate("_SP");
            }
            if(value==2){
                Settings.setTemplate("_SP");
                $('#divSmsGateWayList_SP').addClass('hide');
                $('#divSmsGateWayList_SP').empty();
            }
            //fixed dom issue
            $('.select2-container').css('width','100%');
        });

        if($('select[name="student_attendance_notification"]').val()) {
            $('select[name="student_attendance_notification"]').trigger('change');

        }
        if($('select[name="employee_attendance_notification"]').val()) {
            $('select[name="employee_attendance_notification"]').trigger('change');
        }
        if($('select[name="fee_payment_notification"]').val()) {
            $('select[name="fee_payment_notification"]').trigger('change');
        }
        if($('select[name="message_center_notification"]').val()) {
            $('select[name="message_center_notification"]').trigger('change');
        }
        if($('select[name="circular_notification"]').val()) {
            $('select[name="circular_notification"]').trigger('change');
        }
        if($('select[name="homework_notification"]').val()) {
            $('select[name="homework_notification"]').trigger('change');
        }
        if($('select[name="pre_admission_interview_notification"]').val()) {
            $('select[name="pre_admission_interview_notification"]').trigger('change');
        }
        if($('select[name="promote_students_notification"]').val()) {
            $('select[name="promote_students_notification"]').trigger('change');
        }
        $('input[name="attendance_type"]').on('ifChecked', function(){
            var selected_value = $('input[name="attendance_type"]:checked').val();
            if (selected_value == 'session_attendance') {
                $('#number_of_session').empty();
                Settings.numOfSession()
                $('#number_of_session').show();    
            } else  {
                $('#number_of_session').hide();    
                $('#number_of_session').empty();
                $('#attendance_session_input').empty();
            }
        });

        $('body').on('change', '#number_of_session_input', function() {

            var number_of_session = $('#number_of_session_input').val();
            if(number_of_session>= 1) {
                var input = '';
                    if(number_of_session == 1) {
                    input += '<div class="row" >'+
                            '<div class="col-md-2">'+
                                '<div class="form-group has-feedback">'+
                                    '<label for="day_end">Session</label>'+
                                '</div>'+
                            '</div>'+
                            '<div class="col-md-2">'+
                                '<div class="form-group has-feedback">'+
                                    '<label for="evening_start">From</label>'+
                                '</div>'+
                            '</div>'+
                            '<div class="col-md-2">'+
                                '<div class="form-group has-feedback">'+
                                    '<label for="evening_end">To</label>'+
                                '</div>'+
                            '</div>'+
                            '<div class="col-md-6"></div>'+
                        '</div>';
                    }
                    input += '<div class="row">'+
                            '<div class="col-md-1">'+
                                '<div class="form-group has-feedback">'+
                                    '<label for="attendance_session_number">'+number_of_session+'</label>'+
                                '</div>'+
                            '</div>'+
                            '<div class="col-md-2">'+
                                '<div class="form-group has-feedback">'+
                                    '<input type="text" class="form-control time_picker"  readonly name="attendance_session_from[]" placeholder="time" value="" required   minlength="7" maxlength="8" />'+
                                    '<span class="fa fa-clock-o form-control-feedback"></span>'+
                                    '<span class="text-danger"></span>'+
                                '</div>'+
                            '</div>'+
                            '<div class="col-md-2">'+
                                '<div class="form-group has-feedback">'+
                                    '<input type="text" class="form-control time_picker"  readonly name="attendance_session_to[]" placeholder="time" value="" required   minlength="7" maxlength="8" />'+
                                    '<span class="fa fa-clock-o form-control-feedback"></span>'+
                                    '<span class="text-danger"></span>'+
                                '</div>'+
                            '</div>'+
                            '<div class="col-md-2">'+
                                '<a href="javascript:;" class="btn btn-danger btn-md" id="remove_session">'+
                                    '<span class="glyphicon glyphicon-remove"></span>'+  
                                '</a>'+
                            '</div>'+
                             '<div class="col-md-4"></div>'+
                        '</div>';
                
                $('#attendance_session_input').append(input);
                $('#attendance_session_input').show();
                $(".time_picker").datetimepicker({
                    format: 'LT',
                    showClear: true,
                    ignoreReadonly: true
                });
            }
        });
        $('body').on('click', '#add_session', function() {
            var number_of_session = parseInt($('#number_of_session_input').val());
            $('#number_of_session_input').val(number_of_session+1);
            $('#number_of_session_input').trigger('change');
        });

        $('body').on('click', '#remove_session', function() {
            var number_of_session = parseInt($('#number_of_session_input').val());
            if(number_of_session > 1) {
                $('#number_of_session_input').val(number_of_session-1);
                $(this).parent().parent().remove();
            }
        });
    }

    static numOfSession() {
        let input = '<div class="form-group">'+
            '<label for="number_of_session">Number Of Sessions'+ 
                '<i class="fa fa-question-circle" data-toggle="tooltip" data-placement="bottom" title="" data-original-title="Number Of Sessions"></i>'+
            '</label>'+
            '<div><label>'+
                '<input type="text" class="form-control" readonly id="number_of_session_input" name="number_of_session" placeholder="Number Of Sessions" value="1" required="" minlength="1">'+
                '</label><a href="javascript:;" class="btn btn-success btn-md" id="add_session">'+
                '<span class="glyphicon glyphicon-plus"></span>'+
            '</a></div>'+
            '<span class="text-danger"></span>'+
            '</div>'
            $('#number_of_session').append(input);
            $('#number_of_session_input').trigger('change');
    }
    static setSmsGateWay(which) {
        //add html dom
        let gatewayhtml = '<div class="form-group has-feedback">\n' +
            '<label for="sms_gateway">SMS Gateway\n' +
            '<i class="fa fa-question-circle" data-toggle="tooltip" data-placement="bottom" title="" data-original-title="SMS Gateway to send sms"></i>\n' +
            '<span class="text-danger">*</span>\n' +
            '</label>\n' +
            '<select name="sms_gateway'+which+'" placeholder ="Pick a sms gateway..." class="form-control" required>\n' +
            '</select>\n' +
            '<span class="form-control-feedback"></span>\n' +
            '</div>';
        $('#divSmsGateWayList'+which).empty();
        $('#divSmsGateWayList'+which).append(gatewayhtml);

        //now call api and get data
        Generic.loaderStart();
        axios.get(window.smsGatewayListURL)
            .then((response) => {
                if (Object.keys(response.data).length) {
                    $('select[name="sms_gateway'+which+'"]').empty().prepend('<option selected=""></option>').select2({allowClear: true,placeholder: 'Pick a gateway...', data: response.data});
                    
                    //now if set selected value
                    if(which == "_St"){
                        $('select[name="sms_gateway_St"]').val(window.gatewaySt).trigger('change');
                    }else if(which == "_PM"){
                        $('select[name="sms_gateway_PM"]').val(window.gatewayPM).trigger('change');
                    }else if(which == "_MC"){
                        $('select[name="sms_gateway_MC"]').val(window.gatewayMC).trigger('change');
                    }else if(which == "_HW"){
                        $('select[name="sms_gateway_HW"]').val(window.gatewayHW).trigger('change');
                    }else if(which == "_CC"){
                        $('select[name="sms_gateway_CC"]').val(window.gatewayCC).trigger('change');
                    }else if(which == "_PA"){
                        $('select[name="sms_gateway_PA"]').val(window.gatewayPA).trigger('change');
                    }else if(which == "_SP"){
                        $('select[name="sms_gateway_SP"]').val(window.gatewaySP).trigger('change');
                    }
                    else{
                        $('select[name="sms_gateway_Emp"]').val(window.gatewayEmp).trigger('change');
                    }
                }
                else {
                    $('select[name="sms_gateway'+which+'"]').empty().select2({placeholder: 'Pick a gateway...'});
                    toastr.error('There are no gateway created!');
                }
                Generic.loaderStop();
            }).catch((error) => {
            let status = error.response.statusText;
            toastr.error(status);
            Generic.loaderStop();

        });

        //init select 2 and show the dom
        $('select[name="sms_gateway'+which+'"]').select2();
        $('#divSmsGateWayList'+which).removeClass('hide');
    }
    static setTemplate(which) {
        //add html dom
        let templateHtml = '<div class="form-group has-feedback">\n' +
            '<label for="notification_template">Notification template\n' +
            '<i class="fa fa-question-circle" data-toggle="tooltip" data-placement="bottom" title="" data-original-title="Which template use in notification"></i>\n' +
            ' <span class="text-danger">*</span>\n' +
            ' </label>\n' +
            ' <select name="notification_template'+which+'" placeholder ="Pick a template..." class="form-control" required></select>\n' +
            ' <span class="form-control-feedback"></span>\n' +
            ' </div>';
        $('#divTemplateList'+which).empty();
        $('#divTemplateList'+which).append(templateHtml);

       //now call api and get data
        Generic.loaderStart();

        // let templateType = (which == "_St") ?
        //     $('select[name="student_attendance_notification"]').val() :
        //         ((which == "_PM")? $('select[name="fee_payment_notification"]').val(): ((which == "_PA")? $('select[name="pre_admission_interview_notification"]').val(): $('select[name="employee_attendance_notification"]').val()));
        let templateType = '';
        if(which == "_St") {
            templateType = $('select[name="student_attendance_notification"]').val();
        } else if(which == "_PM") {
            templateType = $('select[name="fee_payment_notification"]').val();
        } else if(which == "_PA") {
            templateType = $('select[name="pre_admission_interview_notification"]').val();
        } else if(which == "_SP") {
            templateType = $('select[name="promote_students_notification"]').val();
        } else {
            templateType = $('select[name="employee_attendance_notification"]').val();
        }
        let userType = (which == "_St" || which == "_PM" || which == "_PA" || which == "_SP") ? "student" : "employee";
        let getURL = window.templateListURL + "?type="+templateType+"&user="+userType;
        axios.get(getURL)
            .then((response) => {
                if (Object.keys(response.data).length) {
                    $('select[name="notification_template'+which+'"]').empty().prepend('<option selected=""></option>').select2({allowClear: true,placeholder: 'Pick a template...', data: response.data});
                    //now if set selected value
                    if(which == "_St"){
                        $('select[name="notification_template_St"]').val(window.templateSt).trigger('change');
                    }else if(which == "_PM"){
                        $('select[name="notification_template_PM"]').val(window.templatePM).trigger('change');
                    }else if(which == "_PA"){
                        $('select[name="notification_template_PA"]').val(window.templatePA).trigger('change');
                    }else if(which == "_SP"){
                        $('select[name="notification_template_SP"]').val(window.templateSP).trigger('change');
                    }
                    else{
                        $('select[name="notification_template_Emp"]').val(window.templateEmp).trigger('change');
                    }
                }
                else {
                    $('select[name="notification_template'+which+'"]').empty().select2({placeholder: 'Pick a template...'});
                    toastr.error('There are no template created!');
                }
                Generic.loaderStop();
            }).catch((error) => {
            let status = error.response.statusText;
            toastr.error(status);
            Generic.loaderStop();

        });

        //init select 2 and show dom
        $('select[name="notification_template'+which+'"]').select2();
        $('#divTemplateList'+which).removeClass('hide');
    }


    static reportInit() {
        Generic.initCommonPageJS();
        $('.my-colorpicker').colorpicker();

        $('.documentUp').change(function(){
            var input = this;
            var url = $(this).val();
            var ext = url.substring(url.lastIndexOf('.') + 1).toLowerCase();
            if (input.files && input.files[0]&& (ext == "png" || ext == "jpeg" || ext == "jpg"))
            {
                //validate size
                var sizeImg =input.files[0].size/1024;
                if (sizeImg>1024) {
                    toastr.error("File is too big!");
                    $(input).val('');
                    return false
                }
            }
            else{
                $(input).val('');
            }
        });
    }

}
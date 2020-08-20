import Generic from "./Generic";

export default class Academic {
    /**
     * academic related codes
     */
    static bussInit() {
        Generic.initCommonPageJS();
        Generic.processDeleteDialog();
    }
    static iclassInit() {
        Generic.initCommonPageJS();
        Generic.initDeleteDialog();
    }
    static sectionInit() {
        Generic.initCommonPageJS();
        Generic.initDeleteDialog();
    }
    static subjectInit() {
        Generic.initCommonPageJS();
        Generic.initDeleteDialog();
        $('#class_id_filter').on('change', function () {
            let class_id = $(this).val();
            let getUrl = window.location.href.split('?')[0];
            if(class_id){
                getUrl +="?class="+class_id;

            }
            window.location = getUrl;

        });
    }
    static studentInit() {
        Generic.initCommonPageJS();
        Generic.initDeleteDialog();
        $('select[name="nationality"]').on('change', function () {
            // console.log('fire me');
            var value = $(this).val();
            if(value == 'Other'){
                $('input[name="nationality_other"]').prop('readonly', false);
            }
            else{
                $('input[name="nationality_other"]').val('');
                $('input[name="nationality_other"]').prop('readonly', true);
            }
        });

        $('select[name="class_id"]').on('change', function () {
            let class_id = $(this).val();
            let sectionElement = $(this).attr('data-sectionElement');
            Academic.getSection(class_id, sectionElement);

        });

        $('#student_add_edit_class_change').on('change', function () {
            //get subject of requested class
            Generic.loaderStart();
            let class_id = $(this).val();
            let type = (institute_category == "college") ? 0 : 2;
            Academic.getSubject(class_id, type, function (res={}) {
                // console.log(res);
                if (Object.keys(res).length){

                    $('select[name="fourth_subject"]').empty().prepend('<option selected=""></option>').select2({placeholder: 'Pick a subject...', data: res});

                }
                else{
                    // clear subject list dropdown
                    $('select[name="fourth_subject"]').empty().select2({placeholder: 'Pick a subject...'});
                    toastr.warning('This class have no subject!');
                }
                Generic.loaderStop();
            });
            if(institute_category == "college") {
                Generic.loaderStart();
                Academic.getSubject(class_id, 1, function (res={}) {
                    // console.log(res);
                    if (Object.keys(res).length){

                        $('select[name="alt_fourth_subject"]').empty().prepend('<option selected=""></option>').select2({placeholder: 'Pick a subject...', data: res});

                    }
                    else{
                        // clear subject list dropdown
                        $('select[name="alt_fourth_subject"]').empty().select2({placeholder: 'Pick a subject...'});
                        toastr.warning('This class have no subject!');
                    }
                    Generic.loaderStop();
                });

            }
        });

        $('#student_list_filter').on('change', function () {
            let class_id = $('select[name="class_id"]').val();
            let section_id = $(this).val();
            let urlLastPart = '';
            if(institute_category == 'college'){
                let ac_year = $('select[name="academic_year"]').val();
                if(!ac_year){
                    toastr.error('Select academic year!');
                    return false;
                }

                urlLastPart ="&academic_year="+ac_year;
            }
            if(class_id && section_id){
                let getUrl = window.location.href.split('?')[0]+"?class="+class_id+"&section="+section_id+urlLastPart;
                window.location = getUrl;

            }

        });
        $('select[name="academic_year"]').on('change', function () {
            $('#student_list_filter').trigger('change');
        });

        $('.getStudyCert').click(function() {
            var studentID = $(this).attr('data-studentID');
            if(studentID != '') {
                Generic.loaderStart();
                $.ajax({
                    type: 'GET',
                    url: window.studyCertificate,
                    data: { studentID: studentID },
                    success:function(resp) {
                        $('.modal-body').empty();
                        $('.modal-body').html(resp.html);
                        Generic.loaderStop();
                        $('#modalStudyCertificateForm').modal('show');
                    }
                });
            }
        });

        // Pre Admission
        $(".date_time_picker").datetimepicker({
            format: "YYYY-MM-DD HH:mm:ss",
            ignoreReadonly: true
        });

        $('input.tableCheckedAll').on('ifToggled', function (event) {
            var tid = $(this).closest('table').attr('id');
            var oTable = window[tid];
            var allPages = oTable.column( 0 ).nodes( );
        
            var chkToggle;
            $(this).is(':checked') ? chkToggle = "check" : chkToggle = "uncheck";
            var table = $(event.target).closest('table');
            $(allPages).find('input:checkbox:not(.tableCheckedAll)').iCheck(chkToggle);
            setStudentIDs();
        });

        $('input.rowCheckedAll').on('ifToggled', function (event) {
            setStudentIDs();
        });

        $('#scheduleInterview').click(function() {
            if($('#studentIDs').val() != '') {
                $('#datetime').val('');
                $('#modalSetInteviewForm').modal('show');
            }
        });

        $('#scheduleInterviewForm').validate({
            onkeyup: false,
            onfocusout: false,
            errorElement: 'p',
            rules: {
                datetime: {
                    required: true
                },
                studentIDs: {
                    required: true
                }
            },
            messages: {
                datetime: {
                    required: 'Please select date and time.',
                }
            },
            errorElement: "em",
            errorPlacement: function (error, element) {
                error.addClass("help-block");
                error.insertAfter(element);

            },
            highlight: function (element, errorClass, validClass) {
                $(element).parents(".form-group").addClass("has-error").removeClass("has-success");
            },
            unhighlight: function (element, errorClass, validClass) {
                $(element).parents(".form-group").addClass("has-success").removeClass("has-error");
            }
        });
        function setStudentIDs() {
            var studentIDs = [];
            $('#listDataTableWithSearch td input:checkbox:not(.tableCheckedAll):checked').each(function() {
                studentIDs.push($(this).val());
            });
            $('#studentIDs').val(studentIDs);
        }
        // Pre Admission

        // Promotion
        $('#promoteStudents').click(function() {
            if($('#studentIDs').val() != '') {
                $('#modalSetInteviewForm').modal('show');
            }
        });

        $('#promoteStudentsForm').validate({
            onkeyup: false,
            onfocusout: false,
            errorElement: 'p',
            rules: {
                academic_year_id: {
                    required: true
                },
                class_id: {
                    required: true
                },
                sectionID: {
                    required: true
                },
                studentIDs: {
                    required: true
                }
            },
            messages: {
                academic_year_id: {
                    required: 'Please select academic year.',
                },
                class_id: {
                    required: 'Please select class.',
                },
                sectionID: {
                    required: 'Please select section.',
                }
            },
            errorElement: "em",
            errorPlacement: function (error, element) {
                error.addClass("help-block");
                error.insertAfter(element);

            },
            highlight: function (element, errorClass, validClass) {
                $(element).parents(".form-group").addClass("has-error").removeClass("has-success");
            },
            unhighlight: function (element, errorClass, validClass) {
                $(element).parents(".form-group").addClass("has-success").removeClass("has-error");
            }
        });
        // Promotion

    }
    static  getZone(bus_id) {
        let getUrl = window.zone_list_url + "?bus=" + bus_id;
        if (bus_id) {
            Generic.loaderStart();
            axios.get(getUrl)
                .then((response) => {
                    console.log('rd', response.data);
                    console.log('rdl', Object.keys(response.data).length);

                    if (Object.keys(response.data).length) {
                        $('select[name="zone_id"]').empty().prepend('<option selected=""></option>').select2({allowClear: true,placeholder: 'Pick a zone...', data: response.data});
                        setTimeout(function(){
                            console.log('window.zoneId', window.zoneId);
                            if(window.zoneId && window.zoneId !== "0") {
                                $('select[name="zone_id"]').val(window.zoneId).change();
                            } 
                        }, 100);
                    }
                    else {
                        $('select[name="zone_id"]').empty().select2({placeholder: 'Pick a zone...'});
                        toastr.error('This bus has not assigned to any zone!');
                    }
                    Generic.loaderStop();
                }).catch((error) => {
                let status = error.response.statusText;
                toastr.error(status);
                Generic.loaderStop();

            });
        }
        else {
            // clear section list dropdown
            $('select[name="zone_id"]').empty().select2({placeholder: 'Pick a zone...'});
        }
    }
    static  getSection(class_id, sectionElement = '') {
        let getUrl = window.section_list_url + "?class=" + class_id;
        let element = (sectionElement != '') ? $('select[name="'+sectionElement+'"]') : $('select[name="section_id"]');
        if (class_id) {
            Generic.loaderStart();
            axios.get(getUrl)
                .then((response) => {
                    if (Object.keys(response.data).length) {
                        element.empty().prepend('<option selected=""></option>').select2({allowClear: true,placeholder: 'Pick a section...', data: response.data});
                        setTimeout(function(){
                            console.log('window.sectionId', window.sectionId);
                            if(window.sectionId && window.sectionId !== "0") {
                                element.val(window.sectionId).change();
                            } 
                        }, 100);
                    }
                    else {
                        element.empty().select2({placeholder: 'Pick a section...'});
                        toastr.error('This class have no section!');
                    }
                    Generic.loaderStop();
                }).catch((error) => {
                let status = error.response.statusText;
                toastr.error(status);
                Generic.loaderStop();

            });
        }
        else {
            // clear section list dropdown
            element.empty().select2({placeholder: 'Pick a section...'});
        }
    }

    static  getSubjectAttendance(class_id, subjectElement = '') {
        let getUrl = window.subject_list_url + "?class=" + class_id;
        let element = (subjectElement != '') ? $('select[name="'+subjectElement+'"]') : $('select[name="subject_id"]');
        if (class_id) {
            Generic.loaderStart();
            axios.get(getUrl)
                .then((response) => {
                    if (Object.keys(response.data).length) {
                        element.empty().prepend('<option selected=""></option>').select2({allowClear: true,placeholder: 'Pick a subject...', data: response.data});
                        setTimeout(function(){
                            console.log('window.subjectId', window.subjectId);
                            if(window.subjectId && window.subjectId !== "0") {
                                element.val(window.subjectId).change();
                            } 
                        }, 100);
                    }
                    else {
                        element.empty().select2({placeholder: 'Pick a subject...'});
                        toastr.error('This class have no subject!');
                    }
                    Generic.loaderStop();
                }).catch((error) => {
                let status = error.response.statusText;
                toastr.error(status);
                Generic.loaderStop();

            });
        }
        else {
            // clear section list dropdown
            element.empty().select2({placeholder: 'Pick a subject...'});
        }
    }
    
    static  getSubject(class_id, $exam_id,$type=0, cb) {
        let getUrl = window.subject_list_url + "?class=" + class_id+"&type="+$type+"&exam_id="+$exam_id;
        if (class_id) {
            axios.get(getUrl)
                .then((response) => {
                    cb(response.data);

                }).catch((error) => {
                let status = error.response.statusText;
                toastr.error(status);
                cb();

            });
        }
        else {
            cb();
        }
    }
    static getStudentByAcYearAndClassAndSection(acYear=0, classId, sectionId, cb=function(){}) {
        let getUrl = window.getStudentAjaxUrl +"?academic_year="+acYear+"&class="+classId+"&section="+sectionId;
        axios.get(getUrl)
            .then((response) => {
                // console.log(response);
                cb(response.data);
            }).catch((error) => {
            let status = error.response.statusText;
            toastr.error(status);
            cb([]);
        });
    }

    static chaptersInit() {
        Generic.initCommonPageJS();
        Generic.initDeleteDialog();

        $('select[name="class_id"]').on('change', function () {
            let class_id = $(this).val();
            Academic.getSubjectAttendance(class_id, 'subject');
        });
        $('select[name="subject"]').on('change', function () {
            let class_id =  $('select[name="class_id"]').val();
            let subject = $(this).val();
            let queryString = "?class="+class_id+"&subject="+subject;
    
            let getUrl = window.location.href.split('?')[0]+queryString;
            window.location = getUrl;
        });

        function showSubjects() {
            var cid = $(this).data('id');
            var html = '<div class="row">';
            var subjects = class_subjects[cid] ? class_subjects[cid] : [];
            if(!subjects.length) {
                html += '<div class="col-md-12 text-center"><strong>There is no subjects in this class.</strong>'
            } else {
                subjects.forEach( subject => {
                    var id = subject.id;
                    var name = subject.name;
                    var count = subject.chapters_count;
                    html += `<div class="col-lg-4 col-md-6 col-sm-6">
                                        <div class="row sprcard ">
                                            <div class="col-lg-12 sprcard-body ">
                                                <div class="col-lg-12 section-identifier classheader school-class-${id}">
                                                    <strong>${name}</strong>
                                                </div>
                                            </div>
                                            <div class="col-lg-12 totalattendance no-right-border">
                                                <div class="col-lg-6 padding-0">
                                                    <span class="text">Chapters</span>
                                                </div>
                                                <div class="col-lg-6 padding-0">
                                                    <span class="count">${count}</span>
                                                </div>
                                            </div>
                                            <div class="sprcard-footer col-lg-12">
                                                <a href="/chapter?class=${cid}&amp;subject=${id}" class="viewatt card-link">View</a>
                                                <a href="/chapter/create?class=${cid}&amp;subject=${id}" class="addatt card-link">Add</a>
                                            </div>
                                        </div>
                                    </div>`;
                });
            }
            html += '</div>';
            $('#subjects-model .modal-body').html(html);
            $('#subjects-model').modal('show');
        }

        $('.class-link').on('click', showSubjects);
    }
    /**
     * Student Attendance
     */
    static attendanceInit() {
        Generic.initCommonPageJS();
        Generic.initDeleteDialog();

        $('select[name="class_id"]').on('change', function () {
            let class_id = $(this).val();
            Academic.getSection(class_id);
            let attendance_type =  ($('#check_attendance_type').val() !=  undefined) ? $('#check_attendance_type').val() : '';
            attendance_type =  attendance_type.trim();
            if(attendance_type == 'subject') {
                Academic.getSubjectAttendance(class_id);
            }
        });
        $('select[name="section_id"]').on('change', function () {
            let queryDate = $('input[name="queryDate"]').val();
            if(queryDate){
                $('#attendance_list_filter').trigger('dp.change');
            }
        });
        $('select[name="subject_id"], select[name="session_id"]').on('change', function () {
            $('#attendance_list_filter').trigger('dp.change');
        });
        $('#attendance_list_filter').on('dp.change', function (event) {
            let atDate = $(this).val();
            let classId = $('select[name="class_id"]').val();
            let sectionId = $('select[name="section_id"]').val();
            let acYearId = $('select[name="academic_year"]').val();
            let queryDate = $('input[name="queryDate"]').val();
            let attendance_type =  ($('#check_attendance_type').val() !=  undefined) ? $('#check_attendance_type').val() : '';
            attendance_type =  attendance_type.trim();
            let $dynamicQueryString = '';
            //check year, class, section and date is fill up then procced
            if(attendance_type == 'subject') {
                let subjectId = $('select[name="subject_id"]').val();
                if(!atDate || !classId || !sectionId || !subjectId){
                    toastr.warning('Fill up class, section, subject and date first!');
                    return false;
                }
                $dynamicQueryString = "&subject_id="+subjectId;
               
            } else if(attendance_type == 'session') {
                let sessionId = $('select[name="session_id"]').val();
                if(!atDate || !classId || !sectionId || !sessionId){
                    toastr.warning('Fill up class, section, session and date first!');
                    return false;
                }
                $dynamicQueryString = "&session_id="+sessionId;
            } else {
                if(!atDate || !classId || !sectionId){
                    toastr.warning('Fill up class, section and date first!');
                    return false;
                }
            }
            
            if(institute_category == "college" && !acYearId){
                toastr.warning('Select academic year first!');
                return false;
            }
        
            if(!queryDate) {
                queryDate = 'attendance_date';
            }

            let queryString = "?class="+classId+"&section="+sectionId+"&"+queryDate+"="+atDate+$dynamicQueryString;
            
            if(institute_category == 'college'){
                queryString +="&academic_year="+acYearId;
            }

            let getUrl = window.location.href.split('?')[0]+queryString;
            window.location = getUrl;

        });

        $('.attendanceExistsChecker').on('dp.change', function (event) {
            Academic.checkAttendanceExists(function (data) {
                    if(data>0){
                        toastr.error('Attendance already exists!');
                    }
                    else{
                        $('#section_id_filter').trigger('change');
                    }
            });

        });
        $('#toggleCheckboxes').on('ifChecked ifUnchecked', function(event) {
            if (event.type == 'ifChecked') {
                $('input:checkbox:not(.notMe)').iCheck('check');
            } else {
                $('input:checkbox:not(.notMe)').iCheck('uncheck');
            }
        });

        $('#section_id_filter').on('change', function () {
            //hide button
            let sectionId = $(this).val();
            let classId =  $('select[name="class_id"]').val();
            let acYearId =  $('select[name="academic_year"]').val();
            let queryDate = $('input[name="attendance_date"]').val();
            //validate input
            if(!classId || !sectionId || !queryDate){
                return false;
            }
            //check year then procced
            if(institute_category == "college"){
                if(!acYearId) {
                    toastr.warning('Select academic year first!');
                    return false;
                }
            }
            else {
                acYearId = 0;
            }

            Generic.loaderStart();
            Academic.checkAttendanceExists(function (data) {
                if(data>0){
                    toastr.error('Attendance already exists!');
                }

                Generic.loaderStop();

            });


        });

        $('input.inTime').on('dp.change', function (event) {
            let attendance_date = window.moment($('input[name="attendance_date"]').val(),'DD-MM-YYYY');
            let inTime =  window.moment(event.date,'DD-MM-YYYY');
            if(inTime.isBefore(attendance_date)){
                toastr.error('In time can\'t be less than attendance date!');
                $(this).data("DateTimePicker").date(attendance_date.format('DD/MM/YYYY, hh:mm A'));
                return false;
            }

            let timeDiff = window.moment.duration(inTime.diff(attendance_date));
           
            if(timeDiff.days()>0){
                toastr.error('In time can\'t be greater than attendance date!');
                $(this).data("DateTimePicker").date(attendance_date.format('DD/MM/YYYY, hh:mm A'));
                return false;
            }

        });

        $('input.outTime').on('dp.change', function (event) {
            let inTime = window.moment($(this).parents('tr').find('input.inTime').val(),'DD-MM-YYYY, hh:mm A');
            let outTime =  window.moment(event.date,'DD-MM-YYYY, hh:mm A');

            if(outTime.isBefore(inTime)){
                toastr.error('Out time can\'t be less than in time!');
                $(this).data("DateTimePicker").date(inTime);
                return false;
            }
            let timeDiff = window.moment.duration(outTime.diff(inTime));
            if(timeDiff.days()>0){
                toastr.error('Can\'t stay more than 24 hrs!');
                $(this).data("DateTimePicker").date(inTime);
                return false;
            }
            let workingHours = [timeDiff.hours(), timeDiff.minutes()].join(':');
            $(this).parents('tr').find('span.stayingHour').text(workingHours);

        });

        $('#outTimeSuject, #inTimeSuject').on('dp.change', function (event) {
            var inTimeSuject = $('#inTimeSuject').data("DateTimePicker").date();
            var outTimeSuject =  $('#outTimeSuject').data("DateTimePicker").date();

            let outTime =  window.moment(outTimeSuject,'DD-MM-YYYY, hh:mm A');
            let inTime =  window.moment(inTimeSuject,'DD-MM-YYYY, hh:mm A');
            if(outTime.isBefore(inTime)){
                toastr.error('Out time can\'t be less than in time!');
                $(this).data("DateTimePicker").date(inTime);
                return false;
            }
            let timeDiff = window.moment.duration(outTime.diff(inTime));
            if(timeDiff.days()>0){
                toastr.error('Can\'t stay more than 24 hrs!');
                $(this).data("DateTimePicker").date(inTimeSuject);
                return false;
            }

            // console.log(timeDiff.hours());
            if(!isNaN(timeDiff.hours()) && !isNaN(timeDiff.minutes())) {
                let workingHours = [timeDiff.hours(), timeDiff.minutes()].join(':');
                $('.stayingHour').text(workingHours);   
                var inTimeVal = $('#inTimeSuject').val();
                var outTimeVal = $('#outTimeSuject').val();
                $(".inTime").val(inTimeVal);
                $(".outTime").val(outTimeVal);
            }
         });

        
        // $('#inTimeSuject, #outTimeSuject').on('dp.change', function(e){
        //     var inTimeSuject = $('#inTimeSuject').val();
        //     var outTimeSuject = $('#outTimeSuject').val();
        //     $(".inTime").val(inTimeSuject);
        //     $(".outTime").val(outTimeSuject);

        //     if(inTimeSuject != '' && outTimeSuject != '') {
        //         axios.post(get_time_difference, { 'inTimeSuject': inTimeSuject, 'outTimeSuject':outTimeSuject })
        //         .then((response) => {
        //             $('.stayingHour').empty();
        //             $('.stayingHour').append(response.data);   
        //         }).catch((error) => {
        //             let status = error.statusText;
        //             toastr.error(status);

        //         });
        //     }
        // });
    }

    /**
     * Student Attendance
     */
    static busAttendanceInit() {
        Generic.initCommonPageJS();
        Generic.initDeleteDialog();

        $('select[name="bus_id"]').on('change', function () {
            let bus_id = $(this).val();
            Academic.getZone(bus_id);
        });
        $('select[name="zone_id"]').on('change', function () {
            let queryDate = $('input[name="queryDate"]').val();
            if(queryDate){
                $('#attendance_list_filter').trigger('dp.change');
            }
        });
        $('#attendance_list_filter').on('dp.change', function (event) {
            let atDate = $(this).val();
            let busId = $('select[name="bus_id"]').val();
            let zoneId = $('select[name="zone_id"]').val();
            let acYearId = $('select[name="academic_year"]').val();
            let queryDate = $('input[name="queryDate"]').val();

            //check year, class, section and date is fill up then procced
            if(!atDate || !busId || !zoneId){
                toastr.warning('Fill up bus, zone and date first!');
                return false;
            }
            if(institute_category == "college" && !acYearId){
                toastr.warning('Select academic year first!');
                return false;
            }

            if(!queryDate) {
                queryDate = 'attendance_date';
            }

            let queryString = "?bus="+busId+"&zone="+zoneId+"&"+queryDate+"="+atDate;
            if(institute_category == 'college'){
                queryString +="&academic_year="+acYearId;
            }

            let getUrl = window.location.href.split('?')[0]+queryString;
            window.location = getUrl;

        });

        $('.attendanceExistsChecker').on('dp.change', function (event) {
            Academic.checkBusAttendanceExists(function (data) {
                    if(data>0){
                        toastr.error('Attendance already exists!');
                    }
                    else{
                        $('#zone_id_filter').trigger('change');
                    }
            });

        });
        $('#toggleCheckboxes').on('ifChecked ifUnchecked', function(event) {
            if (event.type == 'ifChecked') {
                $('input:checkbox:not(.notMe)').iCheck('check');
            } else {
                $('input:checkbox:not(.notMe)').iCheck('uncheck');
            }
        });

        $('#zone_id_filter').on('change', function () {
            //hide button
            let zoneId = $(this).val();
            let busId =  $('select[name="bus_id"]').val();
            let acYearId =  $('select[name="academic_year"]').val();
            let queryDate = $('input[name="attendance_date"]').val();
            //validate input
            if(!busId || !zoneId || !queryDate){
                return false;
            }
            //check year then procced
            if(institute_category == "college"){
                if(!acYearId) {
                    toastr.warning('Select academic year first!');
                    return false;
                }
            }
            else {
                acYearId = 0;
            }

            Generic.loaderStart();
            Academic.checkBusAttendanceExists(function (data) {
                if(data>0){
                    toastr.error('Attendance already exists!');
                }

                Generic.loaderStop();

            });


        });
    }

    static checkBusAttendanceExists(cb={}) {
        let atDate = $('input[name="attendance_date"]').val();
        let busId = $('select[name="bus_id"]').val();
        let zoneId = $('select[name="zone_id"]').val();
        let acYearId = $('select[name="academic_year"]').val();
        let queryString = "?bus="+busId+"&zone="+zoneId+"&attendance_date="+atDate;

        if(institute_category == 'college'){
            queryString +="&academic_year="+acYearId;
        }

        let getUrl = window.attendanceUrl + queryString;
        axios.get(getUrl)
            .then((response) => {
              cb(response.data);
            }).catch((error) => {
            let status = error.response.statusText;
            toastr.error(status);
            cb(0);
            Generic.loaderStop();
        });

    }

    static checkAttendanceExists(cb={}) {
        let atDate = $('input[name="attendance_date"]').val();
        let classId = $('select[name="class_id"]').val();
        let sectionId = $('select[name="section_id"]').val();
        let acYearId = $('select[name="academic_year"]').val();
        let attendance_type =  ($('#check_attendance_type').val() !=  undefined) ? $('#check_attendance_type').val() : '';
        attendance_type =  attendance_type.trim();
        let $dynamicQueryString = '';
            //check year, class, section and date is fill up then procced
        if(attendance_type == 'subject') {
            let subjectId = $('select[name="subject_id"]').val();
            if(!atDate || !classId || !sectionId || !subjectId){
                toastr.warning('Fill up class, section, subject and date first!');
                return false;
            }
            $dynamicQueryString = "&subject_id="+subjectId;
            
        } else if(attendance_type == 'session') {
            let sessionId = $('select[name="session_id"]').val();
            if(!atDate || !classId || !sectionId || !sessionId){
                toastr.warning('Fill up class, section, session and date first!');
                return false;
            }
            $dynamicQueryString = "&session_id="+sessionId;
        }
        let queryString = "?class="+classId+"&section="+sectionId+"&attendance_date="+atDate+$dynamicQueryString;

        if(institute_category == 'college'){
            queryString +="&academic_year="+acYearId;
        }

        let getUrl = window.attendanceUrl + queryString;
        axios.get(getUrl)
            .then((response) => {
              cb(response.data);
            }).catch((error) => {
            let status = error.response.statusText;
            toastr.error(status);
            cb(0);
            Generic.loaderStop();
        });

    }

    static attendanceFileUploadStatus() {
        // progress status js code here
        $.ajax({
            'url': window.fileUploadStatusURL,
        }).done(function(r) {
            if(r.success) {
                $('#statusMessage').html(r.msg);
                setTimeout(function () {
                    window.location.reload();
                }, 5000);
            } else {
                $('#statusMessage').html(r.msg);
                if(r.status == 0){
                    setTimeout(function () {
                        Academic.attendanceFileUploadStatus();
                    }, 500);
                }
                else if(r.status == -1){
                    $('.progressDiv').removeClass('alert-info');
                    $('.progressDiv').addClass('alert-danger');
                    $('#spinnerspan').remove();
                }

            }
        }).fail(function() {
                $('#statusMessage').html("An error has occurred...Contact administrator" );
            });

    }

    static studentProfileInit() {
        $('.btnPrintInformation').click(function () {
            $('ul.nav-tabs li:not(.active)').addClass('no-print');
            $('ul.nav-tabs li.active').removeClass('no-print');
            window.print();
        });

        $('#tabAttendance').click(function () {
            let id = $(this).attr('data-pk');
            let geturl = window.attendanceUrl+'?student_id='+id;
            Generic.loaderStart();
            $('#attendanceTable tbody').empty();
            axios.get(geturl)
                .then((response) => {
                   // console.log(response);
                   if(response.data.length){
                       response.data.forEach(function (item) {
                           let color = item.present == "Present" ? 'bg-green' : 'bg-red';
                          let trrow = ' <tr>\n' +
                              '  <td class="text-center">'+item.attendance_date+'</td>\n' +
                              '  <td class="text-center"> <span class="badge '+ color+'">'+item.present+'</span></td>\n' +
                              '</tr>';

                           $('#attendanceTable tbody').append(trrow);
                       });
                   }

                    Generic.loaderStop();
                }).catch((error) => {
                let status = error.response.statusText;
                toastr.error(status);
                Generic.loaderStop();
            });
        });
    }

    static examRuleTemplateInit() {
        Generic.initCommonPageJS();
        Generic.initDeleteDialog();

        var total_exam_marks = 0;
        var over_all_pass = 0;

        $(document).on('click', '.removemdt', function(){
            var _this = this;
            swal({
                title: 'Are you sure?',
                text: 'You will not be able to recover filled information',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, remove it!',
                cancelButtonText: 'No, keep it'
            }).then((result) => {
                if(result.value) {
                    var id = $(_this).data('id');
                    if(id) {
                        Generic.loaderStart();
                        var url = window.location.origin + '/exam/rule/template/rule/' + id;
                        axios.delete(url)
                        .then((response) => {
                            if(response.data.success) {
                                $(_this).closest('tr').remove();
                                toastr.success(response.data.message);
                            } else {
                                toastr.error(esponse.data.message);
                            }
                            Generic.loaderStop();
                        }).catch((error) => {
                            toastr.error(error);
                            Generic.loaderStop();
                        });
                    } else {
                        $(_this).closest('tr').remove();
                    }
                }
            });
        });

        function fetchGradeInfo() {
            $('input[name="total_exam_marks"]').val('');
            $('input[name="over_all_pass"]').val('');
            let gradeId = $('select[name="grade_id"]').val();
            if (gradeId) {
                let getUrl = window.grade_details_url + "?grade_id=" + gradeId;
                Generic.loaderStart();
                axios.get(getUrl)
                    .then((response) => {
                        // console.log(response.data);
                        $('input[name="total_exam_marks"]').val(response.data.totalMarks);
                        $('input[name="over_all_pass"]').val(response.data.passingMarks);
                        total_exam_marks = response.data.totalMarks;
                        over_all_pass = response.data.passingMarks;
                        Generic.loaderStop();
                    }).catch((error) => {
                        let status = error.response.statusText;
                        toastr.error(status);
                        Generic.loaderStop();
                    });
            }
        }

        $('select[name="grade_id"]').on('change', function () {
            fetchGradeInfo();
        });
        
        $('select[name="passing_rule"]').on('change', function () {
            let passingRule = $(this).val();
            if (passingRule == 2) {
                // individual pass
                $('input[name="over_all_pass"]').val(0);
                $('input[name="pass_marks[]"]').prop('readonly', false);
                $('input[name="pass_marks[]"]').val(0);
            }
            else {
                if ($('input[name="over_all_pass"]').val() == 0) {
                    fetchGradeInfo();
                }
                $('.overAllPassDiv').show();
            }

            if (passingRule == 1) {
                $('input[name="pass_marks[]"]').prop('readonly', true);
            }
            else {
                $('input[name="pass_marks[]"]').prop('readonly', false);
                $('input[name="pass_marks[]"]').val(0);
            }
        });

        function rulesValidation() {
            $('.subjectlist').each(function(i, s) {
                console.log( $(s).find('select.combi_subject'));
                $(s).find('select.combi_subject').on('change', function () {
                    let subjectId = $(s).find('select[name="subject_id[]"]').val();
                    let combineSujectId = $(this).val();
                    if (combineSujectId.includes(subjectId)) {
                        toastr.error("Same subject can not be a combine subject!");
                        var selecteValues = combineSujectId.filter(function (e) { return e !== subjectId });
                        $(s).find('select[name="combine_subject_id[]"]').val(selecteValues).trigger('change');
                    }
                });
            });
            $('.subjectlist').each(function(i, s) {
                console.log( $(s).find('select[name="subject_id[]"]'));
                $(s).find('select.subject').on('change', function () {
                    var _this = this;
                    var selectedSubs = $('select.subject').map(function(){
                        return this != _this && this.value;
                    }).get();
                    var selectedComb = $('.combi_subject').map(function(){
                        return this.value;
                    }).get();
                    let subjected = selectedSubs.concat(selectedComb);
                    if (subjected.includes($(this).val())) {
                        toastr.error("Same subject can not be selected multiple times!");
                        $(this).val("");
                    }
                });
            });

            $('.mdtlist').each(function(i, mdt) {
                console.log($(mdt).closest('table'));
                var index = $(mdt).closest('table').data('index');
                $(document).on('change keyup paste', 'input[name="total_marks['+ index +'][]"]', function () {
                    let grandTotalMakrs = parseInt($('input[name="total_exam_marks"]').val());
                    let distributionTotalMarks = 0;
                    $(this).closest('.mdtlist').find('input[name="total_marks['+ index +'][]"]').each(function (index, element) {
                        if ($(element).val().length) {
                            distributionTotalMarks += parseInt($(element).val());
                        }
                    });
                    // console.log(grandTotalMakrs, distributionTotalMarks);
                    if (distributionTotalMarks > grandTotalMakrs) {
                        toastr.error("Total marks distribution must not be greater than the total grading marks.");
                        $(this).val(0);
                    }
                });
                $(document).on('change keyup paste', 'input[name="existing_total_marks['+ index +'][]"]', function () {
                    let grandTotalMakrs = parseInt($('input[name="total_exam_marks"]').val());
                    let distributionTotalMarks = 0;
                    $(this).closest('.mdtlist').find('input[name="existing_total_marks['+ index +'][]"]').each(function (index, element) {
                        if ($(element).val().length) {
                            distributionTotalMarks += parseInt($(element).val());
                        }
                    });
                    // console.log(grandTotalMakrs, distributionTotalMarks);
                    if (distributionTotalMarks > grandTotalMakrs) {
                        toastr.error("Total marks distribution must not be greater than the total grading marks.");
                        $(this).val(0);
                    }
                });
                $(mdt).find('select.marksdist').on('change', function () {
                    var _this = this;
                    var selectedMDP = $(mdt).find('select.marksdist').map(function(){
                        return this != _this && this.value;
                    }).get();
                    let selected = $(this).val();
                    if (selectedMDP.includes(selected)) {
                        toastr.error("Same mark distrution type can not be selected!");
                        $(this).val('');
                    }
                });
            });
        }
 
    
        function fetchGradeInfo() {
            $('input[name="total_exam_marks"]').val('');
            $('input[name="over_all_pass"]').val('');
            let gradeId =  $('select[name="grade_id"]').val();
            if(gradeId) {
                let getUrl = window.grade_details_url + "?grade_id=" + gradeId;
                Generic.loaderStart();
                axios.get(getUrl)
                    .then((response) => {
                        // console.log(response.data);
                        $('input[name="total_exam_marks"]').val(response.data.totalMarks);
                        $('input[name="over_all_pass"]').val(response.data.passingMarks);
                        Generic.loaderStop();
                    }).catch((error) => {
                    let status = error.response.statusText;
                    toastr.error(status);
                    Generic.loaderStop();
                });
            }
        }

        function addMarkDistType(e) {
            var mdt = $('select.marksdist').map(function(){
                return this.value;
            }).get();
            if(mdt.includes("")) {
                toastr.error("Please complete filling all distribution types before proceed!");
                return;
            }
            var subind = $(e.detail).data('sub');
            $("#mdt-" + subind + " tbody").append(getMDT(subind));

            rulesValidation();
            $('select.select2').select2();
        }

        $("#add_subject").on('click', function(event) {
            var subjectlists = $('.subjectlist').length;
            event.preventDefault();
            var templa = window.getSubjTemp(subjectlists);
            $(".box-body").append(templa +
                '<div class="row">' +
                '<div class="col-md-12">' +
                '<div class="form-group has-feedback">' +
                '<label>Marks Distribution<span class="text-danger">*</span> </label>' +
                '<table data-index="' + subjectlists +'" id="mdt-' + subjectlists +
                '" class="mdtlist table table-striped table-border haveForm">' +
                '<thead>' +
                '<tr>' +
                '<th>' +
                'Type' +
                '</th>' +
                '<th>' +
                'Total Marks' +
                '</th>' +
                '<th>' +
                'Pass Marks' +
                '</th>' +
                '</tr>' +
                '</thead>' +
                '<tbody>' + window.getMDT(subjectlists) +
                '</tbody>' +
                '</table>' +
                '</div>' +
                '</div>' +
                '</div>');
                setTimeout(function(){
                    getSubjects(); 
                    rulesValidation();
                    $('select.select2').select2();
                },100);
        });

    $('#exam_rules_add_class_chang').change(getSubjects);

    function getSubjects(){
        var class_id = $('#exam_rules_add_class_chang').val();
        Generic.loaderStart();
        axios.get(
            '/subjectlist', 
            {
                params: {
                    class_id
                }
            })
            .then((response) => {
                var data = [];
                response.data.forEach(function(item){
                    data.push({
                        id: item.id,
                        text: item.name + ' | ' + item.class.name
                    });
                })
                $('select.subject').select2({data});
                $('select.combi_subject').select2({data});
                Generic.loaderStop();
            }).catch((error) => {
                let status = error.response ? error.response.statusText : error;
                toastr.error(status);
                Generic.loaderStop();
            });
        }

        window.addEventListener("attachValidator", addMarkDistType);

        rulesValidation();
        $('select[name="subject_id[]"]').select2();
    }

    
    static examRuleInit() {
        Generic.initCommonPageJS();
        Generic.initDeleteDialog();
        $('#exam_rules_add_class_change').on('change', function () {
            //get subject of requested class
            Generic.loaderStart();
            let class_id = $(this).val();
            /*Academic.getSubject(class_id, 0, function (res={}) {
                // console.log(res);
                if (Object.keys(res).length){
                    $('select[name="subject_id"]').empty().prepend('<option selected=""></option>').select2({placeholder: 'Pick a subject...', data: res});
                    // $('select[name="combine_subject_id[]"]').empty().prepend('<option selected=""></option>').select2({placeholder: 'Pick a subject...', data: res});
                    $('select[name="combine_subject_id[]"]').empty().select2({placeholder: 'Pick a subject...', data: res});
                }
                else{
                    // clear subject list dropdown
                    $('select[name="subject_id"]').empty().select2({placeholder: 'Pick a subject...'});
                    $('select[name="combine_subject_id[]"]').empty().select2({placeholder: 'Pick a subject...'});
                    toastr.warning('This class have no subject!');
                }
                Generic.loaderStop();
            });*/

            //now fetch exams for this class
            Academic.getExam(class_id);

        });

        $('select[name="exam_id"]').on('change', function () {

            $('#distributionTypeTable tbody').empty();
            let class_id = $('#exam_rules_add_class_change').val() || 0;
            Academic.getSubject(class_id, $(this).val(), 0, function (res={}) {
                // console.log(res);
                if (Object.keys(res).length){
                    $('select[name="subject_id"]').empty().prepend('<option selected=""></option>').select2({placeholder: 'Pick a subject...', data: res});
                    // $('select[name="combine_subject_id[]"]').empty().prepend('<option selected=""></option>').select2({placeholder: 'Pick a subject...', data: res});
                    $('select[name="combine_subject_id[]"]').empty().select2({placeholder: 'Pick a subject...', data: res});
                }
                else{
                    // clear subject list dropdown
                    $('select[name="subject_id"]').empty().select2({placeholder: 'Pick a subject...'});
                    $('select[name="combine_subject_id[]"]').empty().select2({placeholder: 'Pick a subject...'});
                    toastr.warning('This class have no subject!');
                }
                Generic.loaderStop();
            });

            if($(this).val()) {
                let getUrl = window.exam_details_url + "?exam_id=" + $(this).val();
                Generic.loaderStart();
                axios.get(getUrl)
                    .then((response) => {
                        // console.log(response.data);
                        response.data.forEach(function (item) {
                            let trrow = '<tr>\n' +
                                ' <td>\n' +
                                ' <span>' + item.text + '</span>\n' +
                                ' <input type="hidden" name="type[]" value="' + item.id + '">\n' +
                                ' </td>\n' +
                                ' <td>\n' +
                                '<input type="number" class="form-control" name="total_marks[]" value="" required min="0">\n' +
                                '</td>\n' +
                                ' <td>\n' +
                                '<input type="number" class="form-control" name="pass_marks[]" value="0" required min="0">\n' +
                                '</td>\n' +
                                '</tr>';

                            $('#distributionTypeTable tbody').append(trrow);
                        });
                        Generic.loaderStop();
                    }).catch((error) => {
                    let status = error.response.statusText;
                    toastr.error(status);
                    Generic.loaderStop();
                });
            }
        });

        function fetchGradeInfo() {
            $('input[name="total_exam_marks"]').val('');
            $('input[name="over_all_pass"]').val('');
            let gradeId =  $('select[name="grade_id"]').val();
            if(gradeId) {
                let getUrl = window.grade_details_url + "?grade_id=" + gradeId;
                Generic.loaderStart();
                axios.get(getUrl)
                    .then((response) => {
                        // console.log(response.data);
                        $('input[name="total_exam_marks"]').val(response.data.totalMarks);
                        $('input[name="over_all_pass"]').val(response.data.passingMarks);
                        Generic.loaderStop();
                    }).catch((error) => {
                    let status = error.response.statusText;
                    toastr.error(status);
                    Generic.loaderStop();
                });
            }
        }

        $('select[name="grade_id"]').on('change', function () {
            fetchGradeInfo();
        });
        $('select[name="combine_subject_id[]"]').on('change', function () {
            let subjectId =  $('select[name="subject_id"]').val();
            let combineSujectId = $(this).val();
            if(combineSujectId.includes(subjectId)){
                toastr.error("Same subject can not be a combine subject!");
                var selecteValues = combineSujectId.filter(function(e) { return e !== subjectId });
                $('select[name="combine_subject_id[]"]').val(selecteValues).trigger('change');
            }
        });
        $('select[name="passing_rule"]').on('change', function () {
            let passingRule = $(this).val();
            if(passingRule == 2) {
                // individual pass
                $('input[name="over_all_pass"]').val(0);
                $('input[name="pass_marks[]"]').prop('readonly', false);
                $('input[name="pass_marks[]"]').val(0);
            }
            else{
                if($('input[name="over_all_pass"]').val() == 0){
                    fetchGradeInfo();
                }
                $('.overAllPassDiv').show();
            }

            if(passingRule == 1){
                $('input[name="pass_marks[]"]').prop('readonly', true);
            }
            else{
                $('input[name="pass_marks[]"]').prop('readonly', false);
                $('input[name="pass_marks[]"]').val(0);
            }
        });

        //
        $('html').on('change keyup paste','input[name="total_marks[]"]', function(){
            let grandTotalMakrs = parseInt($('input[name="total_exam_marks"]').val());
            let distributionTotalMarks = 0;
            $('input[name="total_marks[]"]').each(function (index,element) {
                if($(element).val().length) {
                    distributionTotalMarks += parseInt($(element).val());
                }
            });
            // console.log(grandTotalMakrs, distributionTotalMarks);
            if(distributionTotalMarks> grandTotalMakrs){
                toastr.error("Total marks distribution must not be greater than the total grading marks.");
                $('input[name="total_marks[]"]').val(0);
            }
        });

        //list page js
        $('select[name="class"]').on('change', function () {
            let classId = $(this).val();
            if(classId){
                //now fetch exams for this class
                Generic.loaderStart();
                let getUrl = window.exam_list_url + "?class_id=" + classId;
                axios.get(getUrl)
                    .then((response) => {
                        if (Object.keys(response.data).length) {
                            $('select[name="exam"]').empty().prepend('<option selected=""></option>').select2({allowClear: true,placeholder: 'Pick a exam...', data: response.data});
                        }
                        else {
                            $('select[name="exam"]').empty().select2({placeholder: 'Pick a exam...'});
                            toastr.error('This class have no exam!');
                        }
                        Generic.loaderStop();
                    }).catch((error) => {
                    let status = error.response.statusText;
                    toastr.error(status);
                    Generic.loaderStop();

                });
            }
            else{
                $('select[name="exam"]').empty().select2({placeholder: 'Pick a exam...'});
            }
        });
        $('#exam_rule_list_filter').on('change', function () {
            let classId =  $('select[name="class"]').val();
            let examId =  $('select[name="exam"]').val();
            if(classId && examId){
                let getUrl = window.location.href.split('?')[0]+"?class_id="+classId+"&exam_id="+examId;
                window.location = getUrl;
            }
        });
    }

    static getExam(class_id) {
        let getUrl = window.exam_list_url + "?class_id=" + class_id;
        if (class_id) {
            Generic.loaderStart();
            axios.get(getUrl)
                .then((response) => {
                    if (Object.keys(response.data).length) {
                        $('select[name="exam_id"]').empty().prepend('<option selected=""></option>').select2({allowClear: true,placeholder: 'Pick a exam...', data: response.data});
                    }
                    else {
                        $('select[name="exam_id"]').empty().select2({placeholder: 'Pick a exam...'});
                        toastr.error('This class have no exam!');
                    }
                    Generic.loaderStop();
                }).catch((error) => {
                let status = error.response.statusText;
                toastr.error(status);
                Generic.loaderStop();

            });
        }
        else {
            // clear section list dropdown
            $('select[name="exam_id"]').empty().select2({placeholder: 'Pick a exam...'});
        }
    }

    static marksInit() {
        Generic.initCommonPageJS();
        $("#markForm").validate({
            errorElement: "em",
            errorPlacement: function (error, element) {
                // Add the `help-block` class to the error element
                error.addClass("help-block");
                error.insertAfter(element);

            },
            highlight: function (element, errorClass, validClass) {
                $(element).parents(".form-group").addClass("has-error").removeClass("has-success");
            },
            unhighlight: function (element, errorClass, validClass) {
                $(element).parents(".form-group").addClass("has-success").removeClass("has-error");
            }
        });
        $('#exam_change').on('change', function () {
            Academic.getSubject($('#class_change').val(), $(this).val(), 0 ,function (res = {}) {
                // console.log(res);
                if (Object.keys(res).length) {
                    $('select[name="subject_id"]').empty().prepend('<option selected=""></option>').select2({
                        allowClear: true,
                        placeholder: 'Pick a subject...',
                        data: res
                    });
                }
                else {
                    // clear subject list dropdown
                    $('select[name="subject_id"]').empty().select2({placeholder: 'Pick a subject...'});
                    toastr.warning('This class have no subject!');
                }
                Generic.loaderStop();
            });
        });
        $('#class_change').on('change', function () {
            let class_id = $(this).val();
            if(class_id) {
                //get sections
                Academic.getSection(class_id);
                //get subject of requested class
                Generic.loaderStart();
                // Academic.getSubject(class_id, 0, function (res = {}) {
                //     // console.log(res);
                //     if (Object.keys(res).length) {
                //         $('select[name="subject_id"]').empty().prepend('<option selected=""></option>').select2({
                //             allowClear: true,
                //             placeholder: 'Pick a subject...',
                //             data: res
                //         });
                //     }
                //     else {
                //         // clear subject list dropdown
                //         $('select[name="subject_id"]').empty().select2({placeholder: 'Pick a subject...'});
                //         toastr.warning('This class have no subject!');
                //     }
                //     Generic.loaderStop();
                // });

                //get sections
                Academic.getExam(class_id);
            }
            else{
                $('select[name="section_id"]').empty().select2({placeholder: 'Pick a section...'});
                $('select[name="subject_id"]').empty().select2({placeholder: 'Pick a subject...'});
                $('select[name="exam_id"]').empty().select2({placeholder: 'Pick a exam...'});
            }

        });

        $('input[type="number"]').on('change keyup paste', function () {
            let marksElements = $(this).closest('tr').find('input[type="number"]');
            let totalMarks = 0;
            marksElements.each(function (index, element) {
                let marks = parseFloat($(element).val());
                if(marks){
                    totalMarks += marks;
                }
            });
            $(this).closest('tr').find('input.totalMarks').val(totalMarks.toFixed(2));
        });

        var title = $('title').text() + $('select[name="class_id"] option[selected]').text();
        title += '-'+ $('select[name="section_id"] option[selected]').text();
        title += '-'+ $('select[name="subject_id"] option[selected]').text();
        title += '-'+ $('select[name="exam_id"] option[selected]').text();
        $('title').text(title);

    }

    static resultInit() {
        Generic.initCommonPageJS();
        $('#class_change').on('change', function () {
            let class_id = $(this).val();
            if(class_id) {
                if(!window.generatePage) {
                    //get sections
                    Academic.getSection(class_id);
                }
                //get sections
                Academic.getExam(class_id);
            }
            else{
                $('select[name="section_id"]').empty().select2({placeholder: 'Pick a section...'});
                $('select[name="exam_id"]').empty().select2({placeholder: 'Pick a exam...'});
            }

        });
        var title = $('title').text() + $('select[name="class_id"] option[selected]').text();
        if($('select[name="section_id"]').val()) {
            title += '-' + $('select[name="section_id"] option[selected]').text();
        }
        title += '-'+ $('select[name="exam_id"] option[selected]').text();
        $('title').text(title);

        //marksheetview button click
        $('.viewMarksheetPubBtn').click(function (e) {
            e.preventDefault();
            postForm(this)

        });

        $('#sms-result').on('click', function(e){
            e.preventDefault();
            var self = this;
            swal.mixin({
                input: 'text',
                confirmButtonText: 'Send &rarr;',
                showCancelButton: true,
              }).queue([
                {
                  title: 'Have you verified the results?',
                  html: 'Make sure you have verified the result before you send to parents. If you have already verified, Please type <b>Verified</b> in the text box.'
                }
              ]).then((result) => {
                //   console.log('result.value', result.value);
                if (!result.value || result.value[0].toLowerCase() !== 'verified') {
                    swal.fire(
                        'Review now',
                        'You have said not verified. Please review the result.',
                        'error'
                    ).then((result) => {
                        if (result.value) {
                            $(self).closest('form').submit();
                        }
                    });
                } else {
                    $('input[name="smstrigger"]').val(result.value[0]);
                    $(self).closest('form').submit();
                }
            });
        });

        function postForm(btnElement) {
            let regiNo = $(btnElement).attr('data-regino');
            let pubMarksheetBtn = $(btnElement).hasClass( "viewMarksheetPubBtn" );
            let classId = $('select[name="class_id"]').val();
            let examId = $('select[name="exam_id"]').val();
            let csrf = document.head.querySelector('meta[name="csrf-token"]').content;
            let formHtml = '<form id="marksheedForm" action="'+window.marksheetpub_url+'" method="post" target="_blank" enctype="multipart/form-data">\n' +
                '    <input type="hidden" name="_token" value="'+csrf+'">\n' +
                '    <input type="hidden" name="class_id" value="'+classId+'">\n' +
                '    <input type="hidden" name="exam_id" value="'+examId+'">\n' +
                '    <input type="hidden" name="regi_no" value="'+regiNo+'">\n';
            if(pubMarksheetBtn){
                formHtml += '    <input type="hidden" name="authorized_form" value="1">\n';
            }
            formHtml += '</form>';

            $('body').append(formHtml);
            $('#marksheedForm').submit();
            $('#marksheedForm').remove();
        }
    }

    static homeworkInit() {
        Generic.initCommonPageJS();
        $("#homeworkForm").validate({
            errorElement: "em",
            errorPlacement: function (error, element) {
                // Add the `help-block` class to the error element
                error.addClass("help-block");
                error.insertAfter(element);

            },
            highlight: function (element, errorClass, validClass) {
                $(element).parents(".form-group").addClass("has-error").removeClass("has-success");
            },
            unhighlight: function (element, errorClass, validClass) {
                $(element).parents(".form-group").addClass("has-success").removeClass("has-error");
            }
        });
        $('#class_change').on('change', function () {
            let class_id = $(this).val();
            if(class_id) {
                //get sections
                Academic.getSection(class_id);
                //get subject of requested class
                Generic.loaderStart();
                Academic.getSubject(class_id, 0, 0,function (res = {}) {
                    // console.log(res);
                    if (Object.keys(res).length) {
                        $('select[name="subject_id"]').empty().prepend('<option selected=""></option>').select2({
                            allowClear: true,
                            placeholder: 'Pick a subject...',
                            data: res
                        });
                    }
                    else {
                        // clear subject list dropdown
                        $('select[name="subject_id"]').empty().select2({placeholder: 'Pick a subject...'});
                        toastr.warning('This class have no subject!');
                    }
                    Generic.loaderStop();
                });
                //get sections
            }
            else{
                $('select[name="section_id"]').empty().select2({placeholder: 'Pick a section...'});
                $('select[name="subject_id"]').empty().select2({placeholder: 'Pick a subject...'});
            }

        });

        var title = $('title').text() + $('select[name="class_id"] option[selected]').text();
        title += '-'+ $('select[name="section_id"] option[selected]').text();
        title += '-'+ $('select[name="subject_id"] option[selected]').text();
        $('title').text(title);

    }

    static chapterInit() {
        Generic.initCommonPageJS();
        $("#homeworkForm").validate({
            errorElement: "em",
            errorPlacement: function (error, element) {
                // Add the `help-block` class to the error element
                error.addClass("help-block");
                error.insertAfter(element);
    
            },
            highlight: function (element, errorClass, validClass) {
                $(element).parents(".form-group").addClass("has-error").removeClass("has-success");
            },
            unhighlight: function (element, errorClass, validClass) {
                $(element).parents(".form-group").addClass("has-success").removeClass("has-error");
            }
        });
        $('#class_change').on('change', function () {
            let class_id = $(this).val();
            if(class_id) {
                //get subject of requested class
                Generic.loaderStart();
                Academic.getSubject(class_id, 0, 0,function (res = {}) {
                    // console.log(res);
                    if (Object.keys(res).length) {
                        $('select[name="subject_id"]').empty().prepend('<option selected=""></option>').select2({
                            allowClear: true,
                            placeholder: 'Pick a subject...',
                            data: res
                        });
                    }
                    else {
                        // clear subject list dropdown
                        $('select[name="subject_id"]').empty().select2({placeholder: 'Pick a subject...'});
                        toastr.warning('This class have no subject!');
                    }
                    Generic.loaderStop();
                });
            }
            else{
                $('select[name="subject_id"]').empty().select2({placeholder: 'Pick a subject...'});
            }
    
        });
    
        var title = $('title').text() + $('select[name="class_id"] option[selected]').text();
        title += '-'+ $('select[name="subject_id"] option[selected]').text();
        $('title').text(title);
    
    }

    static preAdminssionInit() {
        Generic.initCommonPageJS();

        let stopchange = false;
        $(document).on('change', 'input.fieldChange', function (e) {
            let that = $(this);
            let type = $(this).attr('data-type');
            if (stopchange === false) {
                let isActive = $(this).prop('checked') ? 1 : 0;
                let pk = $(this).attr('data-pk');
                let newpostUrl = postUrl.replace(/\.?0+$/, pk);
                newpostUrl = newpostUrl + '/' + type;
                axios.post(newpostUrl, { 'status': isActive })
                    .then((response) => {
                        if (response.data.success) {
                            toastr.success(response.data.message);
                        }
                        else {
                            let status = response.data.message;
                            if (stopchange === false) {
                                stopchange = true;
                                that.bootstrapToggle('toggle');
                                stopchange = false;
                            }
                            toastr.error(status);
                        }
                    }).catch((error) => {
                        // console.log(error.response);
                        let status = error.response.statusText;
                        if (stopchange === false) {
                            stopchange = true;
                            that.bootstrapToggle('toggle');
                            stopchange = false;
                        }
                        toastr.error(status);

                    });
            }
        });
    }

}
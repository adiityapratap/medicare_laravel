import Academic from "./Academic";

export default class Generic {
    /**
     * academic related codes
     */
    static initCommonPageJS() {
        window.selectedStudent = {};
        window.baseURL = window.location.origin;
        toastr.options = {
            "closeButton": true,
            "debug": false,
            "newestOnTop": false,
            "progressBar": false,
            "preventDuplicates": false,
            "onclick": null,
            "timeOut": "30000",
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };

        $("#entryForm").validate({
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

        $(".date_picker").datetimepicker({
            format: "DD/MM/YYYY",
            viewMode: 'days',
            ignoreReadonly: true,
        });
        $(".date_picker_with_clear").datetimepicker({
            format: "DD/MM/YYYY",
            viewMode: 'days',
            showClear: true,
            ignoreReadonly: true
        });


        $(".date_picker2").datetimepicker({
            format: "DD/MM/YYYY",
            viewMode: 'years',
            ignoreReadonly: true
        });

        $(".time_picker").datetimepicker({
            format: 'LT',
            showClear: true,
            ignoreReadonly: true
        });

        $(".date_time_picker").datetimepicker({
            format: "DD/MM/YYYY LT",
            viewMode: 'days',
            ignoreReadonly: true
        });

        $('.date_picker_with_disable_days').datetimepicker({
            format: "DD/MM/YYYY",
            viewMode: 'days',
            ignoreReadonly: true,
            daysOfWeekDisabled: window.disableWeekDays,
            useCurrent: false
        });

        $('.only_year_picker').datetimepicker({
            format: "YYYY",
            viewMode: 'years',
            ignoreReadonly: true,
            useCurrent: false
        });

        var buttonCommon = {
            exportOptions: {
                columns: ':not(.notexport)',
                format: {
                    body: function (data, row, column, node) {
                        if(typeof(window.changeExportColumnIndex) !== 'undefined') {
                            var onValue = typeof window.changeExportColumnValue !== 'undefined' ? window.changeExportColumnValue[0] : 'Active';
                            var offValue = typeof window.changeExportColumnValue !== 'undefined' ? window.changeExportColumnValue[1] : 'Inactive';
                            if (column === window.changeExportColumnIndex) {
                                data = /checked/.test(data) ? onValue : offValue;
                            }
                        }
                        return data;
                    }
                }
            }
        };

        //table with out search
        var table = $('#listDataTable').DataTable({
            pageLength: 20,
            lengthChange: false,
            responsive: true,
            stateSave: true,
            buttons: [
                $.extend(true, {}, buttonCommon, {
                    extend: 'copy',
                    text: '<i class="fa fa-files-o"></i>',
                    titleAttr: 'copy',
                }),
                $.extend(true, {}, buttonCommon, {
                    extend: 'csv',
                    text: '<i class="fa fa-file-text-o"></i>',
                    titleAttr: 'csv',
                }),
                $.extend(true, {}, buttonCommon, {
                    extend: 'excel',
                    text: '<i class="fa fa-file-excel-o"></i>',
                    titleAttr: 'Excel',
                }),
                $.extend(true, {}, buttonCommon, {
                    extend: 'pdf',
                    text: '<i class="fa fa-file-pdf-o"></i>',
                    titleAttr: 'pdf',
                    customize: function (doc) {
                        doc.content[1].table.widths = Array(doc.content[1].table.body[0].length + 1).join('*').split('');
                        doc.content[1].alignment = "center";
                    }
                }),
                $.extend(true, {}, buttonCommon, {
                    extend: 'print',
                    text: '<i class="fa fa-print"></i>',
                    titleAttr: 'print',
                })
            ]
        });
        window.listDataTable = table;
        table.buttons().container().appendTo($('.col-sm-6:eq(0)', table.table().container()));

        //table custom
        var table33 = $('#listDataTableOnlyPrint').DataTable({
            lengthChange: false,
            responsive: true,
            paging: false,
            filter: false,
            buttons: [
                $.extend(true, {}, buttonCommon, {
                    extend: 'print',
                    text: '<i class="fa fa-print"></i>',
                    titleAttr: 'print',
                })
            ]
        });
        window.listDataTableOnlyPrint = table33;
        table33.buttons().container().appendTo($('.col-sm-6:eq(0)', table33.table().container()));


        //style table with search
        // Setup - add a text input to each footer cell
        $('#listDataTableWithSearch thead tr').clone(true).appendTo( '#listDataTableWithSearch thead' );
        $('#listDataTableWithSearch thead tr:eq(1) th').each( function (i) {

            if(window.excludeFilterComlumns.indexOf(i) > -1) {
                $(this).html( '' );
                return;
            }

            $(this).html( '<input type="text" placeholder="Search" />' );
            $( 'input', this ).on( 'keyup change', function () {
                if ( table2.column(i).search() !== this.value ) {
                    table2
                        .column(i)
                        .search( this.value )
                        .draw();
                }
            } );
        } );
        var table2 = $('#listDataTableWithSearch').DataTable({
            pageLength: 25,
            lengthChange: false,
            orderCellsTop: true,
            responsive: true,
            stateSave: true,
            buttons: [
                $.extend(true, {}, buttonCommon, {
                    extend: 'copy',
                    text: '<i class="fa fa-files-o"></i>',
                    titleAttr: 'copy',
                }),
                $.extend(true, {}, buttonCommon, {
                    extend: 'csv',
                    text: '<i class="fa fa-file-text-o"></i>',
                    titleAttr: 'csv',
                }),
                $.extend(true, {}, buttonCommon, {
                    extend: 'excel',
                    text: '<i class="fa fa-file-excel-o"></i>',
                    titleAttr: 'Excel',
                }),
                $.extend(true, {}, buttonCommon, {
                    extend: 'pdf',
                    text: '<i class="fa fa-file-pdf-o"></i>',
                    titleAttr: 'pdf',
                    customize: function (doc) {
                        doc.content[1].table.widths = Array(doc.content[1].table.body[0].length + 1).join('*').split('');
                        doc.content[1].alignment = "center";
                    }
                }),
                $.extend(true, {}, buttonCommon, {
                    extend: 'print',
                    text: '<i class="fa fa-print"></i>',
                    titleAttr: 'print',
                })
            ]
        });
        window.listDataTableWithSearch = table2;
        table2.buttons().container().appendTo($('.col-sm-6:eq(0)', table2.table().container()));


        let stopchange = false;
        $('html #listDataTableWithSearch, html #listDataTable, html .g-account').on('change', 'input.statusChange', function (e) {
            let that = $(this);
            if (stopchange === false) {
                let isActive = $(this).prop('checked') ? 1 : 0;
                let pk = $(this).attr('data-pk');
                let newpostUrl = postUrl.replace(/\.?0+$/, pk);
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

        $(".year_picker").datetimepicker({
            format: "YYYY",
            viewMode: 'years',
            ignoreReadonly: true
        });

        $('input').not('.dont-style').iCheck({
            checkboxClass: 'icheckbox_square-blue',
            radioClass: 'iradio_square-blue',
            increaseArea: '20%' /* optional */
        });
        $('.select2').select2();

        $('.select2students').select2({
            ajax: {
                delay: 250, // wait 250 milliseconds before triggering the request
                url: window.baseURL + '/student/search',
                data: function (params) {
                    var query = {
                        search: params.term,
                        page: params.page || 1
                    }

                    if(!window.ignoreAcdemicYear) {
                        query['year'] = $('#academic-year').val()
                    }

                    // Query parameters will be ?search=[term]&page=[page]
                    return query;
                },
            },
            cache: true,
            placeholder: 'Search for students by name.',
            minimumInputLength: 3,
            escapeMarkup: function (markup) { return markup; }, // let our custom formatter work
            templateResult: function(s){
                if (s.loading) {
                    return s.text;
                }
                var seperator = ' | ';
                var name = s.name || '';
                if(s.father_name){
                    name += seperator + s.father_name;
                }
                if(s.mother_name){
                    name += seperator + s.mother_name;
                }
                if(s.guardian){
                    name += seperator + s.guardian;
                }
                
                name += seperator + s.registration[0].class.name + ' ' + s.registration[0].section.name;
                return '<span>' + name + '</span>'
            },
            templateSelection: function(s) {
                if(!s.id){
                    return s.text;
                }
                var seperator = ' | ';
                var name = s.name || '';
                if(s.father_name){
                    name += seperator + s.father_name;
                }
                if(s.mother_name){
                    name += seperator + s.mother_name;
                }
                if(s.guardian){
                    name += seperator + s.guardian;
                }
                selectedStudent = s;  
                
                $('#class').val(s.registration[0].class.id).trigger('change');
                setTimeout(function(){
                    $('#section').val(s.registration[0].section_id)
                }, 500);
                
                name += seperator + s.registration[0].class.name + ' ' + s.registration[0].section.name;
                return name;
            }
        });

    }

    static mergeGridCells(n, selector) {
        var dimension_col = null;
        var columnCount = $(selector + " tr:first th").length;
        for (dimension_col = 0; dimension_col < columnCount; dimension_col++) {
            // first_instance holds the first instance of identical td
            var first_instance = null;
            var rowspan = 1;
            // iterate through rows
            $(selector).find('tr').each(function () {
    
                // find the td of the correct column (determined by the dimension_col set above)
                var dimension_td = $(this).find('td:nth-child(' + dimension_col + ')');
    
                if (first_instance == null) {
                    // must be the first row
                    first_instance = dimension_td;
                } else if (dimension_td.text() == first_instance.text()) {
                    // the current td is identical to the previous
                    // remove the current td
                    dimension_td.remove();
                    ++rowspan;
                    // increment the rowspan attribute of the first instance
                    first_instance.attr('rowspan', rowspan);
                } else {
                    // this cell is different from the last
                    first_instance = dimension_td;
                    rowspan = 1;
                }
            });
            // If cell merging is limited to specific column
            // If so, exclude other columns
            if(typeof n !== undefined && dimension_col > n){
                break;
            }
        }
    }

    static initDeleteDialog() {
        $('html #listDataTableWithSearch, html #listDataTable, html #listDataTableOnlyPrint, html .g-account').on('submit', 'form.myAction', function (e) {
            e.preventDefault();
            var that = this;
            swal({
                title: 'Are you sure?',
                text: 'You will not be able to recover this record!',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'No, keep it'
            }).then((result) => {
                if (result.value) {
                    that.submit();
                }
            });
        });
    }

    static processDeleteDialog() {
        $('button.btn-danger.delete').on('click', function (e) {
            e.preventDefault();
            var that = this;
            swal({
                title: 'Are you sure?',
                text: 'You will not be able to recover this record!',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'No, keep it'
            }).then((result) => {
                if (result.value) {
                    const deleteURL = $('button.btn-danger.delete').data('url');
                    const busID = $('button.btn-danger.delete').data('id');
                    Generic.loaderStart();
                    axios.delete(deleteURL)
                    .then((response) => {
                        if (response.data.success) {
                            $('#bus-' + busID).remove();
                            toastr.success(response.data.message);
                        }
                        else {
                            let status = response.data.message;
                            toastr.error(status);
                        }
                        Generic.loaderStop();
                    }).catch((error) => {
                        // console.log(error.response);
                        let status = error.response.statusText;
                        toastr.error(status);
                        Generic.loaderStop();
                    });
                }
            });
        });
    }

    static initMarksheetPublic() {
        Generic.initCommonPageJS();
        // $('#class_change').val('').trigger('change');
        $('#class_change').on('change', function () {
            let class_id = $(this).val();
            if(class_id) {
                //get sections
                Academic.getExam(class_id);
            }
            else{
                $('select[name="exam_id"]').empty().select2({placeholder: 'Pick a exam...'});
            }

        });
    }
    static loaderStart(){
        // console.log('loader started...');
        $('.ajax-loader').css('display','block');
    } 
    static loaderStop(){
        // console.log('loader stoped...');
        $('.ajax-loader').css('display','none');
    }



    static initFileUploader() {
        Generic.initCommonPageJS();
        $(document).ready(function(){
            $("input[name='mediasource']").on('ifChecked', function () {
                var currentvalue =  $(this).val();
                $("#icheckmediasource").val(currentvalue);
                if (currentvalue == "local") {
                    $("#filediv").removeClass('hidden');
                    $("#urldiv").addClass('hidden');
                } else if (currentvalue == "url") {
                    $("#filediv").addClass('hidden');
                    $("#urldiv").removeClass('hidden');
                }
            });
            $(document).on('click','.add-url',function(e){
                var markup = '<tr><td style="padding-top:10px;"><input type="url" class="form-control" name="fileurl[]" placeholder="File URL" value="" required maxlength="2000"></td><td style="padding-top:10px;">&nbsp;&nbsp;<input type="button" class="btn btn-danger delete-url" value="Delete This"/></td></tr>';
                $("table tbody").append(markup);
            });
            $(document).on('click','.delete-url',function(e){
                var id = $(this).data('id');
                if(!id) {
                    $(this).parents("tr").remove();
                } else {
                    const that = this;
                    swal({
                        title: 'Are you sure?',
                        text: 'You will not be able to recover this record!',
                        type: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, delete it!',
                        cancelButtonText: 'No, keep it'
                    }).then((result) => {
                        if (result.value) {
                            Generic.loaderStart();
                            let newpostUrl = filedeletepath.replace(/\.?0+$/, id);
                            axios.post(newpostUrl)
                            .then(function(response) {
                                Generic.loaderStop();
                                let status = response.data.message;
                                if(response.data.success) {
                                    toastr.success(status);
                                    $(that).parents("tr").remove();
                                } else {
                                    toastr.error(status);
                                }
                            }).catch(function(error){
                                Generic.loaderStop();
                                console.log('error', error.response);
                                console.log('error', typeof error.response.data);
                                if(typeof error.response.data == 'object') {
                                    toastr.error(error.response.data.message);
                                } else {
                                    toastr.error(error.response.data);
                                }
                            })
                        }
                    });
                }
            });

            var uploadedDocumentMap = {}
            window.dpbox = new Dropzone("div#uploader", {
                maxFilesize: 1,
                autoProcessQueue: false,
                url: window.uploadpath,
                acceptedFiles: $('input[name="mimes"]').val(),
                dictDefaultMessage: 'Drag files here, or click to select multiple.',
                uploadMultiple: false,
                parallelUploads: 20,
                addRemoveLinks: true,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                success: function (file, response) {
                    $(window.formID).append('<input type="hidden" name="document[]" value="' + response.name + '">')
                    uploadedDocumentMap[file.name] = response.name
                },
                removedfile: function (file) {
                    file.previewElement.remove()
                    var name = ''
                    if (typeof file.file_name !== 'undefined') {
                        name = file.file_name
                    } else {
                        name = uploadedDocumentMap[file.name]
                    }
                    $(window.formID).find('input[name="document[]"][value="' + name + '"]').remove()
                },
                init: function () {
                    if(window.files && window.source === 'local') {
                        var files = window.files
                        for (var i in files) {
                            var file = files[i]
                            this.options.addedfile.call(this, file)
                            file.previewElement.classList.add('dz-complete')
                            $('#entryForm').append('<input type="hidden" name="document[]" value="' + file.file_name + '">')
                        }
                    }
                    this.on("error", function(file, message){
                        swal.fire({
                            type: 'error',
                            title: 'Oops...',
                            text: message
                        })
                        this.removeFile(file);
                    });
                    this.on("queuecomplete", function () {
                        console.log('que complete');
                        $(window.formID).submit();
                    });
                }
            });
        });
    }

    static getMessage(element, url, token) {
        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: {
                _token: token,
                message: $(element).data('message'),
                type: $(element).data('type'),
            },
            success:function(r){
                $('.modal-dialog').html(r.message);
                $('#mailModal').modal().show();
            },
        });
    }

    static getVoiceDetail(element, url, token) {
        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: {
                _token: token,
                message: $(element).data('message'),
                type: $(element).data('type'),
            },
            success:function(r){
                $('.modal-dialog').html(r.message);
                $('#mailModal').modal().show();
            },
        });
    }
}


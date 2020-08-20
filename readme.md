# school-management-system(SMS)
Another School Management System build with laravel and PHP 7.

# Features
- Application
- Admission
- Attendance
- Exam
- Result
- Certificate
- Fees
- Accounting
- Library
- Hostel
- Employees
- Leave manage
- Reports
- Front-end website

# Installation and use

## Dependency
- Linux OS
- PHP >= 7.3
- OpenSSL PHP Extension
- PDO PHP Extension
- Mbstring PHP Extension
- Tokenizer PHP Extension
- XML PHP Extension
- Ctype PHP Extension
- JSON PHP Extension
- Laravel Userstamps
- NodeJS, npm, webpack


```
$ git clone https://gitlab.com/sprinkleway/sprinkleschool/

OR development branch

$ git clone https://gitlab.com/sprinkleway/sprinkleschool/ -b development --single-branch
```
```
$ cd sprinkleschool
```
```
$ cp .env.example .env
```
**Change configuration according to your need in ".env" file and create Database**
```
$ composer install
```
```
$ php artisan migrate
```
```
$ php artisan db:seed
```
**Load demo data**
```
$ php artisan db:seed --class DemoSiteDataSeeder
$ php artisan db:seed --class DemoAppDataSeeder
$ php artisan db:seed --class PermissionTableSeeder
```
**Clear cache**
```
$ sudo php artisan cache:clear
```
```
$ npm install
```
```
$ npm run backend-prod # there is a backend-watch command also available for development
```
```
$ npm run frontend-prod # there is a frontend-watch command also available for development
```
```
$ php artisan serve
```
Now visit and login: http://localhost:8000 \
username: admin\
password: demo123

# Demo Live Link
website url: https://www.sprinkleway.com/ \
app url: https://erp.sprinkleway.com/login \
username: admin\
password: demo123

# Screenshot
## Back Panel
<img src="./screenshot/dashboard.png" >
<img src="./screenshot/site-dashboard.png" >

## Front website
<img src="./screenshot/home.png" >

# Custom Form Components

- File upload component

Use `{{ Form::fileUpload("MIME TYPES") }}` to include the file uploader to a form. Also add the following script to the `extraScript` section and modify accordingly.
Example: Refer: `resources/views/backend/medialibrary/demo.blade.php`
```
    @section('extraScript')
        <script src="{{ asset(mix('/js/dropzone.js')) }}"></script>

        <script type="text/javascript">
            Dropzone.autoDiscover = false;

            window.formID = '#demoForm';  // <-- FORM ID of the respective form.
            window.files = {!! json_encode($files) !!} // <-- Already uploaded files to display in the file browser.

            Generic.initFileUploader();

            $(document).on('click','#submitdemo',function(e){  // <-- Submit button ID of the form.
                $('#submitdemo').attr('disabled', 'disabled').find('i').addClass('fa-spin');

                var mediatypevalue =  $('#icheckmediasource').val();
                console.log('mediatypevalue', mediatypevalue);
                if (mediatypevalue == "local") {
                    window.dpbox.processQueue();
                } else if (mediatypevalue == "url"){
                    $(window.formID).submit();
                }

            });
        </script>
    @endsection
```

There are helper methods to assist the file saving & association to your model. To access these methods include `FileUploadTrait` by adding `use App\Traits\FileUploadTrait;` and place `use FileUploadTrait;` inside the controller class. Refer `MediaManagerController`.

To save the files, In your controller add this `$this->processfiles($request, $model, 'foldername');` after saving the `model` object. Here `$request` is the `Request` object & `$model` is the `Model` object which is associated to the files. And `foldername` is the folder which you want to store the files.

To retreive the files. In your controller add this `$files = $this->retrieveFiles($model, 'foldername')` this method returns `array` of file `URLs`. Here variables are same as explained before.

That's it.

# Security Vulnerabilities

If you discover a security vulnerability within SMS, please send an e-mail to Support via [info@sprinkleway.com](mailto:info@sprinkleway.com). All security vulnerabilities will be promptly addressed.

# License

SprinkleSchool is a propriatroy software developed and maintaine by [Sprinkleway Technologies](https://www.sprinkleway.com/). Frameworks and libraries has it own licensed

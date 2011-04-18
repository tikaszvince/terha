TErHa - Templatable PHP  Error Handler class
============================================

Original idea from Tyrael (https://github.com/Tyrael/php-error-handler)

 
Error handler which can provide you an easy way to handle errors even non
recoverable errors like `E_ERROR`, `E_PARSE`, `E_CORE_ERROR`, `E_COMPILE_ERROR`.
 
It wraps the error to an ErrorException instance and throws the
exception (for catchable errors) or calls the default exception handler (for
fatal errors.

BEWARE: you cannot continue the execution of your script on such errors, but
with this script, you can gracefully terminate.
 
@author Vince Tikász 4image#dev|WSE#dev

Requirements
------------
Require PHP > 5.2
For SQLite loging You need PDO_SQLITE

Setup
=====
Include errorhandler.php to Your code. In this case you can ready to use error
handler in developer mode.

Available Profiles
==================
With profiles you can setup error display, log or reporting configurations, and 
also the error page.

## Dev
With *dev* profile every error will be displayed, and logged into a text file under
`logs/dev/` directory. Every day new `error-{date}.log` file will be created.

In *dev* mode, error handler not send reporting mail. Triggered errors not displayed
at the place where triggered but the end of generated page with trace in HTML mode,
covered with an HTML `div` element.

If there is no other option than display error page `profiles/dev/layout.php` will be
use as template.

## Test
With *test* profile every error will be displayed, and logged into a text file under
`logs/test/` directory. Every day new `error-{date}.log` file will be created.

In *test* mode, error handler not send reporting mail. Triggered errors not displayed
at the place where triggered but the end of generated page with trace in text mode,
covered in a HTML comment block

If there is no other option than display error page `profiles/dev/layout.ph`p will be
use as template (the same as in *dev* mode).

## Prod
With *prod* profile no errors will be displayed, but logged into an SQLite database
under `logs/prod/` directory. Every day new `error-{date}.sqlite.db` file will be created.

In *prod* mode, error handler send reporting mail in every 60 minutes.
Logged error text will be contains trace, blank text.

If there is no other option than display error page `profiles/prod/layout.php` will be
use as template.

## Custom profile
You can customize any profile if you create `errorhandler.ini` and set next to your
`index.php`.

You have to set every setting into `[ERROR_HANDLING]` block. Or You can copy any
predefined profile and make your own.

## Configuration

### `ENVIRONMENT`
Value should be the name of an available profile.

### `display_error` (Bool)
If value is `true` every error will be displayed. You can customize display mode with
`show_trace`, `group_display`, `display_mode`, `group_mode` settings.

### error_reporting [Bool] (Not in use)
Sets which PHP errors are reported

### `log_errors` (String)
Error handler should log triggered errors? If false no logs wil be writen.
Error handler can write log into plain text files or SQLite database.

`log_errors` setting has two parts separated with @ character. First part is the
log mode; it could be `file` or `sqlite`. Second part is the path to log file.
You can use tokens to set dinamic parts to the path.

- `{__DIRNAME__}` will replaced with the path of directory contains `errorhandler.php`
- `{__APPDIR__}` will replaced with the path of directory of `SCRIPT_FILENAME`
- `{w}` or `{W}` will replaced with value of `date('W')`
- `{date}` will replaced with value of `date('Y.m.d')`
- `{m}` will replaced with value of `date('m')`
- `{Ym}` will replaced with value of `date('Y.m')`
- `{Yw}` or `{YW}` will replaced with value of `date('Y.w')`


### `show_trace` (Bool)
If `true` trace to triggered error will be shown.

### `group_display` (Bool)
If true ErrorHandler will collect triggered errors and display them on the end of
generated page. You can customize displaying mode with `show_trace`,
`display_mode`, `group_mode` settings.

If `display_error` is false errors not will be displayed.

### `display_mode` `(DM_BLANK|DM_HTML)`
If value is `DM_BLANK` errors will be displayd as plain text messages.
if value is `DM_HTML HTML` formated error messages will be displayed.

If `display_error` is false errors not will be displayed.

### `group_mode` `(GDM_COMMENT|GDM_DIV)`
If you use group_display you can customize the displaing mode of grouped errors.
If you set `GDM_COMMENT` collected errors will covered in a HTML comment on the end
of generated page.
If you set `GDM_DIV` collected errors will covered in a HTML `div` element.

If `display_error` is false errors not will be displayed. 
If `group_display` is false errors not will be collected but send to the output.

### `error_page_template`
If there is no other option than display error page this file will be used as template.
You could set a relative path from the `errorhandler.php`.

### `base_url`
This URL will used in template file as base href url.

### `mail`
If value of mail is false no mail will be sent about triggered errors. If you 
need error reporting mails you should create a `[mail]` INI block in your 
`<profile>.ini` or in your `errorhandler.ini` with the folowing settings

- `period` Minutes between two error reporting email.
- `to` The email address to send error reportin email.
- `from` The From email addres of reporting email
- `subject` The subject of reporting email.


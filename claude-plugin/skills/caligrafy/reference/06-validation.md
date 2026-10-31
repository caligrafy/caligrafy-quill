# 6. Validation

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

> **Verified against framework source (see gotchas.md):**
> - `check()` returns an ARRAY both on success with filters (the filtered data) and on failure (errors). Prefer `filter()` then `validate()` (gotchas G6).
> - Rules other than `required` are skipped for empty values (verified by the QA suite).

> **Where this fits.** These GUMP rules and filters are what you write in the `$rules` and `$filters` of a [capability](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#service): the framework applies them on every call, and pages, REST and AI agents all get the same message for each field. The rules also become the input schema that agents and the JS client see. Controllers can still use them directly, as below.

Data validation is key to the success of any application. This framework provides a tight integration with GUMP - a third party library that makes data validation easy and painless.

> Learn more about GUMP [here](https://github.com/Wixel/GUMP)

GUMP provides two powerful features that are the cornerstones for keeping the data clean and secure: validation and filtering. Both mechanisms use built-in rules that are easy to use and apply based on the type of data.

## Data Validation
`Validation` is about checking that the input follows the rules that the application is expecting. It is intended to avoid undesired data that could ultimately cause errors or introduce security vulnerabilities.

### Validator Methods

There are 3 controller methods that could help validate inputs that we suggest using.

```php

   public function validate($inputArray, $ruleArray); 
   // This methods validates the input array based on a set of rules and returns true or an array of error messages keyed by field

   GUMP::is_valid($inputArray, $ruleArray, $customMessageArray); // also callable as $this->validator->is_valid(...)
   // This method validates the input array and uses custom error messages. It returns true or an array of readable (HTML) error messages

   public function check(Array $inputArray, Array validation_rules, Array filtering_rules); 
   // This method combines both validation and filtering (more on this later)

```

### Validators
GUMP relies on a set of rules - the `Validators` - to do this validation.
Below is a curated list of the available `Validators`.

* required `Ensures the specified key value exists and is not empty`
* required_file `Ensures that a file is specified`
* valid_email `Checks for a valid email address`
* max_len,n `Checks key value length, makes sure it's not longer than the specified length. n = length parameter.`
* min_len,n `Checks key value length, makes sure it's not shorter than the specified length. n = length parameter.`
* exact_len,n `Ensures that the key value length precisely matches the specified length. n = length parameter.`
* alpha `Ensure only alpha characters are present in the key value (a-z, A-Z)`
* alpha_numeric `Ensure only alpha-numeric characters are present in the key value (a-z, A-Z, 0-9)`
* alpha_dash `Ensure only alpha-numeric characters + dashes and underscores are present in the key value (a-z, A-Z, 0-9, _-)`
* alpha_space `Ensure only alpha-numeric characters + spaces are present in the key value (a-z, A-Z, 0-9, \s)`
* numeric `Ensure only numeric key values`
* integer `Ensure only integer key values`
* boolean `Checks for PHP accepted boolean values, returns TRUE for "1", "true", "on" and "yes"`
* float `Checks for float values`
* valid_url `Check for valid URL or subdomain`
* url_exists `Check to see if the url exists and is accessible`
* valid_cc `Check for a valid credit card number (Uses the MOD10 Checksum Algorithm)`
* valid_name `Check for a valid format human name`
* contains,n `Verify that a value is contained within the pre-defined value set`
* min_numeric `Determine if the provided numeric value is higher or equal to a specific value`
* max_numeric `Determine if the provided numeric value is lower or equal to a specific value`
* date `Determine if the provided input is a valid date (ISO 8601)`
* starts `Ensures the value starts with a certain character / set of character`
* phone_number `Validate phone numbers that match the following examples: 555-555-5555 , 5555425555, 555 555 5555, 1(519) 555-4444, 1 (519) 555-4422, 1-555-555-5555`
* regex `You can pass a custom regex using the following format: 'regex,/your-regex/'`
* valid_json_string `validate string to check if it's a valid json format`

> See the full list of GUMP Validators [here](https://github.com/Wixel/GUMP/blob/master/README.md)

### Usage 
`Validation` can be used within the context of a `Controller` or any class in the `/application` folder.
This framework integrated the main methods of GUMP into one common method. 

Let's take the example of a `User` model that has a username and a password. Both the username and the password are required. The username must be alphanumeric and the password should be between 6 and 100 characters.
```php
use Caligrafy\Controller;

class UserController extends Controller {
    public function read()
    {

       /* Validate the inputs using the check the inputs according to the described rules 
        * @param array inputArray is the array of inputs
        * @param array field => rule
        * @return true if validation is successful. Otherwise an array of error messages is returned for each erroneous field
        */
       $result = $this->check($inputArray, array(
                    'username' => 'required|alpha_numeric',
	            'password' => 'required|max_len,100|min_len,6')
       );

       // Alternatively, you can use the validate method
      $result = $this->validate($inputArray, array(
                    'username' => 'required|alpha_numeric',
	            'password' => 'required|max_len,100|min_len,6')
       );

       // With Custom Error messages
      $result = $this->validator->is_valid($inputArray, 
                array(
                    'username' => 'required|alpha_numeric',
	            'password' => 'required|max_len,100|min_len,6'
                ),
                array(
                    'username' => ['required' => 'Username is required']
                )
              );

       // Checking if valid
       if ($result !== true) {
           return $result;
       } else {//...}
    }
}

```

## Data Filtering
Filtering is used to make sure that the data is clean, sanitized and that it does not cause any undesired errors or security vulnerabilities. Unlike validation that is meant to either accept or reject the data, filtering is meant to modify the data and clean it from undesirable formats.

### Filters
GUMP relies on a set of filtering rules - the `Filters`. `Filters` can be any PHP functions that return a string. According to the GUMP documentation, you don't need to create your own if a native PHP function exists that does what you want the filter to do.
Below is a curated list of the available `Filters`.

* sanitize_string `Remove script tags and encode HTML entities, similar to GUMP::xss_clean();`
* urlencode `Encode url entities`
* htmlencode `Encode HTML entities`
* sanitize_email `Remove illegal characters from email addresses`
* sanitize_numbers `Remove any non-numeric characters`
* sanitize_floats `Remove any non-float characters`
* trim `Remove spaces from the beginning and end of strings`
* base64_encode `Base64 encode the input`
* base64_decode `Base64 decode the input`
* sha1 `Encrypt the input with the secure sha1 algorithm`
* md5 `MD5 encode the input`
* noise_words `Remove noise words from string`
* json_encode `Create a json representation of the input`
* json_decode `Decode a json string`
* rmpunctuation `Remove all known punctuation characters from a string`
* basic_tags `Remove all layout orientated HTML tags from text. Leaving only basic tags`
* whole_number `Ensure that the provided numeric value is represented as a whole number`
* ms_word_characters `Converts MS Word special characters [“”‘’–…] to web safe characters`
* lower_case `Converts to lowercase`
* upper_case `Converts to uppercase`
* slug `Creates web safe url slug`

### Usage
`Filtering` can be used within the context of a `Controller` or any class in the `/application` folder.
This framework integrated the main methods of GUMP into one common method. 

Let's take the example of a `User` model that has a username and a password. Both the username and password need to be trimmed (remove extra spaces) and sanitized in order to make sure that no scripts are included in them. This is a great way to use filters.
```php
use Caligrafy\Controller;

class UserController extends Controller {
    public function read()
    {

       /* Validate the inputs using the check the inputs according to the described rules 
        * @param array inputArray is the array of inputs
        * @param array validation is array() by default if no validation is needed
        * @param array field => filter_rules
        * @return array filtered results
        */
       $result = $this->check($inputArray, array(), array(
                    'username' => 'trim|sanitize_string',
	            'password' => 'trim|sanitize_string')
       );

       // Alternatively, you can use the filter method
       $result = $this->filter($inputArray, array(
                    'username' => 'trim|sanitize_string',
	            'password' => 'trim|sanitize_string')
       );       
    }
}
```
> Notice that the 2nd argument of the `check` method is an empty array. This is because it is a combined routine that could execute both validation and/or filtering. 

## Validation & Filtering

The `check` method is a framework method that makes the usage of the GUMP validator more straightforward.

`public function check(Array $inputArray, Array validation_rules, Array filtering_rules);`

The check method accepts 3 arrays: 
1) the input array to be checked 
2) validation array
3) filtering array.

+ When both arrays are provided, the `check` function filters and validates at once. If the validation succeeds, it returns an array of the filtered fields. If the validation fails, it returns an array of the erroneous fields with their respective error messages. **Both outcomes are arrays**, so `$result !== true` cannot tell them apart in this mode. When you need both, filter first and then validate:
```php
$data = $this->filter($inputArray, array('username' => 'trim|sanitize_string'));
$result = $this->validate($data, array('username' => 'required|alpha_numeric'));
if ($result !== true) {
    return api(array('errors' => $result)); // $result is ['username' => 'error message']
}
// $data holds the clean values
```
+ When the validation array is given but not the filtering array, the method performs a validation as described earlier.
+ When the filtering array is given but not the validation array, the method performs a filter as described earlier.

+ Rules other than `required` are skipped when a field is empty: `'passcode' => 'min_len,8'` accepts an empty passcode. Add `required` when the field is mandatory.

> GUMP provides more elaborate features to create custom validations and filters. 
> For more details, consult the GUMP library documentation [here](https://github.com/Wixel/GUMP/blob/master/README.md)

## File Validation
If your application requires uploading files, you might want to validate the files before accepting that. The `uploadFile()` helper also refuses any extension that is not in its allow-list (images, documents, audio, video, `zip` and `json` by default, never scripts). Learn more about [helpers](https://github.com/caligrafy/caligrafy-quill/wiki/9.-Helpers-,-Forms-&-REST-API#helpers).
Using the same `check` method, you can use the built-in rules to perform this validation.
```php
use Caligrafy\Controller;

class UserController extends Controller {
    public function read()
    {
       // Validate the $_FILES inputs
       $result = $this->check($_FILES, array(
	'image' => 'required_file|extension,png;jpg')
       );

       // Then move the file to public/uploads
       if ($result === true) {
           $url = uploadFile($this->request->files('image'), array('png', 'jpg'));
       }
    }
}
```

>[Next Section: Learn about Views](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views)

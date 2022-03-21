# **DISCONTINUED**

Installation
============
## Clone Repo, Install Lumen 5.5

1. Get the repo
2. composer install
3. create .env file in root directory and set DB credentials and generate key (see .env.example)
4. Need to set up .htaccess file for http auth, https and callback url from HMRC with no auth
5. don't forget the Laravel installation and config ...Eg: 0755 on the storage dir  
	sudo chmod -R 0755 storage  
	sudo chown www-data:www-data -R storage  
6. run $ php artisan migrate

> You need to build a web client to use this API. I may make a web client public if I get time
---
> Using Lumen 5.5 because of our server requirements. Slightly older PHP version. See the composer.json.
> Currently can only be used with a single VAT number. Use this software at your own risk. I make no guarantees.
---

Notes
=====
## HMRC 
* Developer Hub 
* https://developer.service.hmrc.gov.uk/developer/login
* Docs 
* https://developer.service.hmrc.gov.uk/api-documentation/docs/api/service/vat-api/1.0
* https://developer.service.hmrc.gov.uk/api-documentation/docs/authorisation/user-restricted-endpoints
* https://developer.service.hmrc.gov.uk/api-documentation/docs/reference-guide
* https://developer.service.hmrc.gov.uk/api-documentation/docs/terms-of-use
* https://github.com/hmrc/vat-api/wiki/FAQ
* https://github.com/hmrc/vat-api/wiki/Creating-Test-Agents-in-the-Sandbox
* https://github.com/hmrc/vat-api/wiki/Changelog
* https://developer.service.hmrc.gov.uk/guides/vat-mtd-end-to-end-service-guide/documentation/customer-support.html

---
* Endpoint access level 	Required authorisation token  
* Open access 	No token  
* Application-restricted 	server_token  
* User-restricted 	OAuth 2.0 access_token  

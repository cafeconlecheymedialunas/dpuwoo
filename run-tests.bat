@echo off
set PHP="C:\Users\dex360\AppData\Local\Programs\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win64\php.exe"
set EXT_DIR=C:\Users\dex360\AppData\Local\Programs\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win64\ext

%PHP% -d "extension_dir=%EXT_DIR%" -d "extension=mbstring" -d "extension=dom" -d "extension=json" -d "extension=libxml" -d "extension=tokenizer" -d "extension=xml" -d "extension=xmlwriter" vendor/bin/phpunit %*
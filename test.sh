#!/bin/bash
export PATH="/c/Users/dex360/AppData/Local/Programs/Local/resources/extraResources/lightning-services/php-8.2.29+0/bin/win64:$PATH"

EXT_DIR="/c/Users/dex360/AppData/Local/Programs/Local/resources/extraResources/lightning-services/php-8.2.29+0/bin/win64/ext"

php -d "error_reporting=22519" \
    -d "extension_dir=$EXT_DIR" \
    -d "extension=mbstring" \
    -d "extension=dom" \
    -d "extension=json" \
    -d "extension=libxml" \
    -d "extension=tokenizer" \
    -d "extension=xml" \
    -d "extension=xmlwriter" \
    -d "extension=openssl" \
    -d "extension=curl" \
    vendor/bin/phpunit "$@"
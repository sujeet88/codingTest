A) System Requirements:

1. Linux Server
2. Apache 2.0+ Server
3. PHP 5.4+
4. Enable curl extension
5. Enable register_argc_argv


B) How to Install:

1. Upload all the script on the server
   a.  codingTest/index.php
   b.  codingTest/includes/AuthApi.php
   c.  codingTest/includes/AuthApiException.php

2. Open command line and connect to server via host username & host password

3. Change directory to apitest
   e.g. cd /var/home/xampp/htdocs/codingTest/

4. Run command in command line to post repository issues on the github|bitbucket
   e.g. php index.php sujeet88 sujeet_102 "https://github.com/codingTest/test" "title" "description"

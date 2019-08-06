<?php
class RedditConfig{
    //standard, oauth token fetch, and api request endpoints
    static $ENDPOINT_STANDARD = 'https://www.reddit.com';
    static $ENDPOINT_OAUTH = 'https://oauth.reddit.com';

    //access token configuration from https://ssl.reddit.com/prefs/apps
    static $CLIENT_ID = '';
    static $CLIENT_SECRET = '';

    // Custom user agent as per API directives
    static $CLIENT_USER_AGENT = 'MotoSQL/0.1 by gnualmafuerte';

    // User (bot) credentials
    static $USERNAME = 'motosql';
    static $PASSWORD = '';
}
?>

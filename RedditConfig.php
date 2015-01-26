<?php
class RedditConfig{
    //standard, oauth token fetch, and api request endpoints
    static $ENDPOINT_STANDARD = 'https://www.reddit.com';
    static $ENDPOINT_OAUTH = 'https://oauth.reddit.com';

    //access token configuration from https://ssl.reddit.com/prefs/apps
    static $CLIENT_ID = 'rL05uBLGydiVBg';
    static $CLIENT_SECRET = '5OsiGlX0oc28xIpBh0u0q4Hy3ro';

    // Custom user agent as per API directives
    static $CLIENT_USER_AGENT = 'GabenBot/0.1 by Krimo';

    // User (bot) credentials
    static $USERNAME = 'I_Hail_GabeN';
    static $PASSWORD = 'FXwQ67^gBc^hpusJHkusFof';
}
?>

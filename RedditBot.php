<?php

require_once('RedditConfig.php');

/**
* Reddit API Wrapper for BOT USE ONLY
*/
class RedditBot
{

    private $access_token;
    private $token_type;
    private $auth_mode = 'basic';

    /**
     * Bot constructor : authenticate user automatically on creation
     * @param string $username The bot username
     * @param string $password The bot Password
     */
    public function __construct()
    {
        self::authenticate();
    }

    /**
     * Authenticate user and store auth token in cookie
     * @param string $username The bot username
     * @param string $password The bot Password
     */
    protected function authenticate()
    {
        if (isset($_COOKIE['reddit_token'])) {
            $token_info = explode(":", $_COOKIE['reddit_token']);
            $this->token_type = $token_info[0];
            $this->access_token = $token_info[1];
        } else {
            $authData = sprintf('grant_type=password&username=%s&password=%s', RedditConfig::$USERNAME, RedditConfig::$PASSWORD);

            $token = self::runCurl(RedditConfig::$ENDPOINT_STANDARD . '/api/v1/access_token', $authData, null, true);

            // Store token and type
            if (isset($token->access_token)) {
                $this->access_token = $token->access_token;
                $this->token_type = $token->token_type;

                // Set token cookie for later use
                $cookie_time = 60 * 60 * 24 * 365 + time();  //seconds * minutes = 59 minutes (token expires in 1hr)
                setcookie('reddit_token', "{$this->token_type}:{$this->access_token}", $cookie_time);
            }
        }

        //set API endpoint
        $this->apiHost = redditConfig::$ENDPOINT_OAUTH;
        // Set auth mode for requests
        $this->auth_mode = 'oauth';
    }

    private function runCurl($url, $data = null, $headers = null, $auth = false)
    {
        $ch = curl_init($url);

        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10
            );

        if ($data != null) {
            $options[CURLOPT_POSTFIELDS] = $data;
            $options[CURLOPT_CUSTOMREQUEST] = "POST";
        }

        if ($this->auth_mode == 'oauth') {
            $headers = array("Authorization: {$this->token_type} {$this->access_token}");
            $options[CURLOPT_USERAGENT] = RedditConfig::$CLIENT_USER_AGENT;
            $options[CURLOPT_HEADER] = false;
            $options[CURLINFO_HEADER_OUT] = false;
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        if ($auth) {
            $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $options[CURLOPT_USERPWD] = RedditConfig::$CLIENT_ID . ":" . RedditConfig::$CLIENT_SECRET;
            $options[CURLOPT_SSLVERSION] = 4;
            $options[CURLOPT_SSL_VERIFYPEER] = false;
            $options[CURLOPT_SSL_VERIFYHOST] = 2;
        }

        curl_setopt_array($ch, $options);

        $apiResponse = curl_exec($ch);
        $response = json_decode($apiResponse);

        //check if non-valid JSON is returned
        if ($error = json_last_error()){
            $response = $apiResponse;
        }

        curl_close($ch);

        return $response;
    }

    public function getUser()
    {
        $userUrl = "{$this->apiHost}/api/v1/me";
        return self::runCurl($userUrl);
    }

    /**
    * Send message
    *
    * Send a message to another user, from the current user
    * @link http://www.reddit.com/dev/api/oauth#POST_api_compose
    * @param string $to The name of a existing user to send the message to
    * @param string $subject The subject of the message, no longer than 100 characters
    * @param string $text The content of the message, in raw markdown
    */
    public function sendMessage($to, $subject, $text)
    {
        $urlMessages = "{$this->apiHost}/api/compose";

        $postData = sprintf("to=%s&subject=%s&text=%s", $to, $subject, $text);

        return self::runCurl($urlMessages, $postData);
    }


    public function markRead($id)
    {
        $url = "{$this->apiHost}/api/read_message";

        $postData = sprintf("id=%s", $id);
        return self::runCurl($url, $postData);
    }

  



  public function getMentions(){
    $urlMentions = "{$this->apiHost}/message/mentions.json";
    return self::runCurl($urlMentions);

  }

    /**
    * Needs CAPTCHA
    *
    * Checks whether CAPTCHAs are needed for API endpoints
    * @link http://www.reddit.com/dev/api/oauth#GET_api_needs_captcha.json
    */
    public function getCaptchaReqs()
    {
        $urlNeedsCaptcha = "{$this->apiHost}/api/needs_captcha.json";
        return self::runCurl($urlNeedsCaptcha);
    }

    /**
    * Get user subscriptions
    *
    * Get the subscriptions that the user is subscribed to, has contributed to, or is moderator of
    * @link http://www.reddit.com/dev/api#GET_subreddits_mine_contributor
    * @param string $where The subscription content to obtain. One of subscriber, contributor, or moderator
    */
    public function getSubscriptions($where = "subscriber")
    {
        $urlSubscriptions = "{$this->apiHost}/subreddits/mine/$where";
        return self::runCurl($urlSubscriptions);
    }


    public function getUnread()
    {
        $urlUnread = "{$this->apiHost}/message/unread";
        return self::runCurl($urlUnread);
    }

    /**
    * Add new comment
    *
    * Add a new comment to a story
    * @link http://www.reddit.com/dev/api/oauth#POST_api_comment
    * @param string $name The full name of the post to comment (name parameter in the getSubscriptions() return value)
    * @param string $text The comment markup
    */
    public function addComment($name, $text)
    {
        $response = null;
        if ($name && $text){
            $urlComment = "{$this->apiHost}/api/comment";
            $postData = sprintf("thing_id=%s&text=%s", $name, $text);
            $response = self::runCurl($urlComment, $postData);
        }
        return $response;
    }

    public function searchSub($name, $query)
    {
        $url = sprintf("{$this->apiHost}/r/$name/search?q=%s", $query);

        $r = self::runCurl($url);

        return $r;
    }

    public function getSubredditsNames()
    {
        $arr = array();

        $subs = self::getSubscriptions();

        foreach ($subs->data->children as $sub) {
            array_push($arr, $sub->data->display_name);
        }

        return $arr;
    }

    public function getComments($subreddit, $after = NULL, $count = NULL)
    {
        $url = "{$this->apiHost}/r/$subreddit/comments";

        if (!is_null($after) && !is_null($count)) {
            $url .= sprintf('?after=%s&count=%s', $after, $count);
        }

        $r = self::runCurl($url);

        return $r;

    }
}

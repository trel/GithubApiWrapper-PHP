<?php

    class GithubOAuth extends HTTP
    {
        const BaseURL = 'https://api.github.com';
        const CACert = '/certs/DigiCertHighAssuranceEVRootCA.crt';
        const urlAuthorize = 'https://github.com/login/oauth/authorize';
        const urlAccessToken = 'https://github.com/login/oauth/access_token';

        public $client_id;
        public $client_secret;
        public $access_token;

        public function __construct( $client_id, $client_secret ) {
            if( empty( $client_id ) ) throw new Exception( "Parameter 'client_id' was empty." );
            $this->client_id = $client_id;

            if( empty( $client_secret ) ) throw new Exception( "Parameter 'client_secret' was empty." );
            $this->client_secret = $client_secret;
        }

        public function requestAccessCode( $scope ) {
            if( !empty( $this->access_token ) ) throw new Exception( 'Already authorized.' );
            $urlParams = array( 'client_id' => $this->client_id );

            if( empty( $scope ) ) throw new Exception( "Parameter 'scope' was empty." );
            $urlParams['scope'] = implode( ',', $scope );

            $request = HTTP::buildRequest( GithubOAuth::urlAuthorize, $urlParams );
            header( "Location: $request" );
        }

        public function requestAccessToken( $code ) {
            $urlParams = array(
                'client_id'    => $this->client_id,
                'client_secret'=> $this->client_secret,
                'code'         => $code
            );
            return HTTP::webRequest( 'POST', GithubOAuth::urlAccessToken, $urlParams );
        }

        public function setToken( $accessToken ) {
            if( empty( $accessToken ) ) throw new Exception( "Parameter 'accessToken' was empty." );
            $replace = array(
                'access_token=',
                '&token_type=bearer',
            );
            $this->access_token = trim( str_replace( $replace, array( '', '' ), $accessToken ) );
        }

        public function setTokenFromCode( $code ) {
            $token = $this->requestAccessToken( $code );
            $this->setToken( $token );
        }

        public function getToken() {
            if( empty( $this->access_token ) ) throw new Exception( 'Access token has not yet been set.' );
            return $this->access_token;
        }

        // Can be issued against any resource to get just the HTTP header info.
        public static function httpHEAD( $path, array $urlParams = array() ) {
            $url = GithubOAuth::BaseURL;
            $url .= ( $path[0] == '/' ) ? "{$path}" : "/{$path}";
            $urlParams['access_token'] = $this->access_token;
            $c = curl_init();
            curl_setopt( $c, CURLOPT_URL, $url );
            curl_setopt( $c, CURLOPT_HEADER, 1 );
            curl_setopt( $c, CURLOPT_NOBODY, 1 );
            curl_setopt( $c, CURLOPT_RETURNTRANSFER, 1 );
            curl_exec( $c );
            $headers = curl_getinfo( $c );
            curl_close( $c );
            return json_encode( $headers );
        }

        // Used for retrieving resources.
        public static function  httpGET( $path, array $urlParams = array() ) {
            $url = GithubOAuth::BaseURL;
            $url .= ( $path[0] == '/' ) ? "{$path}" : "/{$path}";
            $urlParams['access_token'] = $this->access_token;
            $c = curl_init();
            $request = HTTP::buildRequest( $url, $urlParams );
            curl_setopt( $c, CURLOPT_URL, $request );
            curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );
            $response = json_decode( curl_exec( $c ) );
            $headers = curl_getinfo( $c );
            curl_close( $c );
            return json_encode( compact( 'headers', 'response' ) );
        }

        // Used for creating resources, or performing custom actions (such as merging a pull request).
        public static function  httpPOST( $path, array $urlParams = array() ) {
            $url = GithubOAuth::BaseURL;
            $url .= ( $path[0] == '/' ) ? "{$path}" : "/{$path}";
            $url .= '?access_token=' . $this->access_token;
            $c = curl_init();
            $requestingToken = $url == GithubOAuth::urlAccessToken;
            curl_setopt( $c, CURLOPT_URL, $url );
            curl_setopt( $c, CURLOPT_POST, 1 );
            curl_setopt( $c, CURLOPT_RETURNTRANSFER, false );
            curl_setopt( $c, CURLOPT_SSL_VERIFYPEER, false );
            curl_setopt( $c, CURLOPT_SSL_VERIFYHOST, 0 );
            curl_setopt( $c, CURLOPT_POSTFIELDS, $requestingToken ? $urlParams : json_encode( $urlParams ) );
            curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );
            $response = $requestingToken ? curl_exec( $c ) : json_decode( curl_exec( $c ) );
            $headers = curl_getinfo( $c );
            curl_close( $c );
            return $requestingToken ? $response : json_encode( compact( 'headers', 'response' ) );
        }

        // Used for updating resources with partial JSON data. For instance, an Issue resource has title and
        // body attributes. A PATCH request may accept one or more of the attributes to update the resource.
        // PATCH is a relatively new and uncommon HTTP verb, so resource endpoints also accept POST requests.
        public static function  httpPATCH( $path, array $urlParams = array() ) {
            $url = GithubOAuth::BaseURL;
            $url .= ( $path[0] == '/' ) ? "{$path}" : "/{$path}";
            $url .= '?access_token=' . $this->access_token;
            $c = curl_init();
            curl_setopt( $c, CURLOPT_URL, $url );
            curl_setopt( $c, CURLOPT_HEADER, false );
            curl_setopt( $c, CURLOPT_CUSTOMREQUEST, 'PATCH' );
            curl_setopt( $c, CURLOPT_POSTFIELDS, json_encode( $urlParams ) );
            curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );
            $response = json_decode( curl_exec( $c ) );
            $headers = curl_getinfo( $c );
            curl_close( $c );
            return json_encode( compact( 'headers', 'response' ) );
        }

        // Used for replacing resources or collections.
        public static function  httpPUT( $path, array $urlParams = array() ) {
            $url = GithubOAuth::BaseURL;
            $url .= ( $path[0] == '/' ) ? "{$path}" : "/{$path}";
            $url .= '?access_token=' . $this->access_token;
            $c = curl_init();
            $putString = stripslashes( json_encode( $urlParams ) );
            $data = tmpfile();
            fwrite( $data, $putString );
            fseek( $data, 0 );
            curl_setopt( $c, CURLOPT_URL, $url );
            curl_setopt( $c, CURLOPT_PUT, true );
            curl_setopt( $c, CURLOPT_INFILE, $data );
            curl_setopt( $c, CURLOPT_BINARYTRANSFER, true );
            curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $c, CURLOPT_INFILESIZE, strlen( $putString ) );
            curl_exec( $c );
            $headers = curl_getinfo( $c );
            curl_close( $c );
            return json_encode( $headers );
        }

        // Used for deleting resources.
        public static function  httpDELETE( $path, array $urlParams = array() ) {
            $url = GithubOAuth::BaseURL;
            $url .= ( $path[0] == '/' ) ? "{$path}" : "/{$path}";
            $urlParams['access_token'] = $this->access_token;
            $c = curl_init();
            curl_setopt( $c, CURLOPT_URL, $url );
            curl_setopt( $c, CURLOPT_POSTFIELDS, json_encode( $urlParams ) );
            curl_setopt( $c, CURLOPT_FOLLOWLOCATION, 1 );
            curl_setopt( $c, CURLOPT_HEADER, 0 );
            curl_setopt( $c, CURLOPT_RETURNTRANSFER, 1 );
            curl_setopt( $c, CURLOPT_CUSTOMREQUEST, 'DELETE' );
            $response = json_decode( curl_exec( $c ) );
            $headers = curl_getinfo( $c );
            curl_close( $c );
            return json_encode( compact( 'headers', 'response' ) );
        }

        private static function buildRequest( $url, $urlParams ) {
            $request = $url;
            if( !empty( $urlParams ) ) {
                foreach( $urlParams as $k => $v ) {
                    $request .= ( strstr( $request, '?' ) ) ? '&' : '?';
                    $request .= ( $k . '=' . $v );
                }
            }
            return $request;
        }
    }

?>
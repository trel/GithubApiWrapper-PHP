<?php

    class GithubPublic extends HTTP
    {
        const BaseURL = 'https://api.github.com';

        public static function executeRequest( $HTTPVerb, $path, array $urlParams = array() ) {
            $url = GithubPublic::BaseURL;
            $url .= ( $path[0] == '/' ) ? "{$path}" : "/{$path}";
            return HTTP::webRequest( $HTTPVerb, $url, $urlParams );
        }
    }

?>
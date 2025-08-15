class AICO_OpenRouter_API {
    public static function call($model, $messages, $json_mode = true) {
        $api_key = get_option('aico_openrouter_api_key');
        if (!$api_key) return ['error' => 'Missing API Key'];

        $body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 1024
        ];

        if ($json_mode) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => site_url(),
                'X-Title' => get_bloginfo('name')
            ],
            'body' => json_encode($body),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
}
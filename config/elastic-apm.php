<?php

return [
    // Sets whether the apm reporting should be active or not
    'active'        => true,
    
    'app' => [
        // The app name that will identify your app in Kibana / Elastic APM
        'appName'       => 'Some App Name',

        // The version of your app
        'appVersion'    => '',
    ],

    'env' => [
        // whitelist environment variables OR send everything
        'env' => ['DOCUMENT_ROOT', 'REMOTE_ADDR']
        //'env' => []
    ],

    'server' => [
        // The apm-server to connect to
        'serverUrl'     => 'http://127.0.0.1:8200',

        // Token for x
        'secretToken'   => null,
        
        // API version of the apm agent you connect to
        'apmVersion'     => 'v1',

        // Hostname of the system the agent is running on.
        'hostname'      => gethostname(),
    ],

    'spans' => [
        // Depth of backtraces
        'backtraceDepth'=> 25,

        // Add source code to span
        'renderSource' => true,

        'querylog' => [
            // Set to false to completely disable query logging, or to 'auto' if you would like to use the threshold feature.
            'enabled' => true,

            // If a query takes longer then 200ms, we enable the query log. Make sure you set enabled = 'auto'
            'threshold' => 200,
        ],
    ],
];

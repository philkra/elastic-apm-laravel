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
    ],
];

# CakePHP SSE Plugin 🚀

> [!WARNING]
> This plugin is under development and not ready for production. Use at your own risk.

A robust **Server-Sent Events (SSE)** plugin for CakePHP, designed with a **Signal + Payload** architecture.

This plugin enables real-time data streaming optimized for **Shared Hosting environments** (where Redis or Node.js might not be available), while remaining fully scalable for high-performance setups. It effectively separates lightweight signaling (file-based) from robust data storage (Database/Cache/Redis).

## 📦 Installation

1.  **Load the Plugin:**

    ```bash
    bin/cake plugin load Sse
    ```

2.  **Create Signal Directory (For FileSignal):**
    Ensure the temporary directory exists and is writable:

    ```bash
    mkdir -p tmp/sse_signals
    chmod 777 tmp/sse_signals
    ```

3.  **(Optional) Migrations:**
    If you plan to use `database` as the payload engine (recommended for production concurrency):

    ```bash
    bin/cake migrations migrate -p Sse
    ```

-----

## ⚙️ Configuration

By default, the plugin is set to **Zero-Config** mode using **File** for signals and **Cache** for payloads.

### Basic Configuration (`app_local.php`)

You can use string aliases for built-in strategies or pass fully qualified class names for custom implementations.

```php
return [
    'Sse' => [
        // --- Payload Engines (Data Storage) ---
        // Options: 'cache', 'database', 'redis'
        // Or your custom class: \App\Sse\MyRabbitMqStrategy::class
        'payload_engine' => 'cache', 
        
        // --- Signal Engines (Wake-up Call) ---
        // Options: 'file' (Recommended for most cases)
        // Or your custom class: \App\Sse\MyRedisSignalStrategy::class
        'signal_engine' => 'file', 

        // --- Extra Options ---
        // 'cache_config' => 'default', // Cache config name if using 'cache' engine
        // 'redis_config' => 'sse_redis', // Redis config name if using 'redis' engine
    ],
];
```

### 🧩 Custom Strategies (Extensibility)

You can implement your own storage logic (e.g., using an external API, RabbitMQ, or specific log files) by implementing the interfaces:

1.  Create your class implementing `Sse\Service\Payload\PayloadStrategyInterface`.
2.  Register it in `app_local.php`:
    ```php
    'payload_engine' => \App\Service\Sse\MyCustomStrategy::class
    ```

### ⚠️ Critical Requirement: Anti-Buffering

For SSE to work, you **must disable buffering** in your web server for the `.sse` extension.

**Nginx (`.ddev/nginx/sse.conf` or production):**

```nginx
location ~ \.sse$ {
    fastcgi_buffering off;
    gzip off;
    add_header 'X-Accel-Buffering' 'no';
    
    # Standard CakePHP Routing
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_pass unix:/run/php-fpm.sock; # Adjust socket path
}
```

**Apache (`.htaccess`):**

```apache
<IfModule mod_deflate.c>
    SetEnvIfNoCase Content-Type "text/event-stream" no-gzip
</IfModule>
```

**CakePHP Routes (`config/routes.php`):**

```php
$routes->setExtensions(['sse']); // Critical!

$routes->scope('/stream', ['controller' => 'Stream'], function (RouteBuilder $routes) {
    $routes->connect('/:action', ['_ext' => 'sse']);
});
```

-----

## 📡 Backend Usage

### 1\. Creating the Stream (Controller)

In your `StreamController`, use the `SseComponent` to open the channel. You can use **Data Hydration (`beforeSend`)** to convert lightweight IDs into full objects just before sending.

```php
// src/Controller/StreamController.php

public function reports()
{
    $this->autoRender = false;
    $userId = $this->Authentication->getIdentity()->get('id');
    $streamKey = 'USER_QUEUE_' . $userId;

    // (Optional) Hydrator: Transforms a "Seed" into a "Tree"
    // This runs inside the loop, ensuring data is fresh at the moment of sending.
    $hydrator = function ($payload) {
        // If we receive just an ID, fetch fresh data from DB
        if (isset($payload['report_id'])) {
            return [
                'event' => 'report_ready',
                'data' => $this->fetchTable('Reports')->get($payload['report_id']),
                'timestamp' => time()
            ];
        }
        return $payload; // Pass simple messages through
    };

    // (Optional) Maintenance: Runs on every loop cycle
    $keepAlive = function () use ($userId) {
        // Ex: Renew "Online" user session status in a short-term cache
        Cache::write('online_' . $userId, true, 'short_term');
    };

    return $this->Sse->stream($streamKey, [
        'poll' => 2,             // Seconds between checks
        'eventName' => 'update', // Default event name
        'beforeSend' => $hydrator,
        'onLoop' => $keepAlive
    ]);
}
```

### 2\. Sending Data (Trigger)

Use the static service from anywhere in your application (Controller, Table, Command, Job).

**Example A: Full Push (Direct Payload)**

```php
use Sse\Service\SseService;

// Sends ready-to-use data. Fast, but serializes JSON.
SseService::push('USER_QUEUE_1', [
    'event' => 'toast',
    'message' => 'Process completed successfully.',
    'type' => 'success'
]);
```

**Example B: Lightweight Signal (Seed Pattern)**

```php
// Sends only an ID. The Controller's 'beforeSend' will hydrate the data.
// Ideal for complex objects or rapidly changing data to avoid "Stale Data".
SseService::push('USER_QUEUE_1', [
    'report_id' => 505
]);
```

-----

## 🖥️ Frontend Usage (JavaScript)

Use the native `EventSource` API. No external libraries required.

```javascript
// Note the .sse extension to trigger Nginx rules
const url = '/stream/reports.sse';
const eventSource = new EventSource(url);

// 1. Connection Established
eventSource.onopen = () => console.log('Stream connected');

// 2. Listen for specific events
eventSource.addEventListener('toast', (e) => {
    const data = JSON.parse(e.data);
    alert(data.message); // "Process completed..."
});

eventSource.addEventListener('report_ready', (e) => {
    const data = JSON.parse(e.data);
    console.log('Report Received:', data.data);
    // Update Vue/React store...
});

// 3. Error Handling
eventSource.onerror = (err) => {
    console.error('Stream Error', err);
    // EventSource reconnects automatically
};
```

---
[© 2025 arodu](https://github.com/arodu) 

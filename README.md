# CakePHP SSE Plugin

A lightweight Server-Sent Events (SSE) implementation using efficient cache-based polling.

## Installation
You can install the plugin via Composer:

```bash
composer require arodu/cakephp-sse
```

Then load the plugin in your `Application.php`:

```php
public function bootstrap(): void
{
    parent::bootstrap();
    $this->addPlugin('Sse');
}
```

or via the CakePHP console:

```bash
bin/cake plugin load Sse
```

## Usage

### 1\. Controller Setup

Load the component and create the stream endpoint.

```php
// src/Controller/NotificationsController.php
public function initialize(): void
{
    parent::initialize();
    $this->loadComponent('Sse.Sse');
}

public function stream()
{
    $this->autoRender = false;
    $userId = $this->Authentication->getIdentity()->get('id');
    $cacheKey = 'user_notifications_' . $userId;

    // Define what data to send when an update is detected
    $callback = function () use ($userId) {
        return $this->Notifications->find()->where(['user_id' => $userId, 'read' => false])->toArray();
    };

    return $this->Sse->stream($callback, $cacheKey, [
        'eventName' => 'new_notification',
        'poll' => 2,      // Check cache every 2 seconds
        'heartbeat' => 15 // Keep-alive every 15 seconds
    ]);
}
```

### 2\. Triggering Updates

Use the `SseTrigger` to update the cache timestamp when data changes (e.g., in a Table `afterSave`).

```php
use Sse\Sse\SseTrigger;

// In your Table or Business Logic
public function afterSave(EventInterface $event, EntityInterface $entity)
{
    if ($entity->user_id) {
        // This signals the SSE loop to run the callback
        SseTrigger::push('user_notifications_' . $entity->user_id);
    }
}
```

### 3\. Frontend (JavaScript)

Consume the stream using the native `EventSource` API.

```javascript
const eventSource = new EventSource('/notifications/stream');

// Listen for the custom event name defined in the Controller
eventSource.addEventListener('new_notification', (event) => {
    const data = JSON.parse(event.data);
    console.log('New Data:', data);
});

eventSource.onerror = (err) => console.error('Stream Error:', err);
```
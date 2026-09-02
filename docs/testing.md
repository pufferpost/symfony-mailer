# Testing

## In a Symfony app

Use the framework's `mailer.test` tooling — assert emails were sent without hitting the API. Because
the transport only runs at `doSend()`, `WebTestCase`/`KernelTestCase` collect messages as usual:

```php
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WelcomeTest extends WebTestCase
{
    public function testItSendsAWelcome(): void
    {
        $client = static::createClient();
        $client->request('POST', '/signup', ['email' => 'jane@example.com']);

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertEmailHtmlBodyContains($email, 'Welcome');
    }
}
```

This exercises your code up to the mailer without calling the API.

## Testing the transport directly

To assert the exact API request, construct the transport with a `MockHttpClient`-backed SDK client:

```php
use PufferPost\Sdk\Client;
use PufferPost\Symfony\ApiTransport;
use PufferPost\Symfony\MailerEmail;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

$requests = [];
$http = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
    $requests[] = ['url' => $url, 'body' => $options['body'] ?? null];

    return new MockResponse(json_encode(['id' => 'msg_1', 'status' => 'accepted']), ['http_code' => 202]);
});

$transport = new ApiTransport(new Client('key_test_…', 'https://api.test', $http));

$transport->send(
    (new MailerEmail())
        ->from('no-reply@acme.com')
        ->to('jane@example.com')
        ->subject('Welcome')
        ->text('Welcome!')
        ->templateId('tpl_welcome')
        ->templateData(['name' => 'Jane']),
);

// assert on $requests[0]['url'] and the decoded JSON body
```

See the package's own `tests/` for worked examples, including the bundle integration test.

<?php

namespace Crm\ScenariosModule\Tests;

use Crm\RempModule\Models\Campaign\Api;
use Crm\ScenariosModule\Repository\ElementsRepository;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Crm\ScenariosModule\Repository\TriggersRepository;
use Crm\UsersModule\Auth\UserManager;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Nette\Utils\DateTime;

class BannerScenariosTest extends BaseTestCase
{
    const BANNER_ID = 999;

    /** @var Api */
    private $campaignApi;

    public function setUp(): void
    {
        parent::setUp();
        $this->campaignApi = $this->inject(Api::class);
    }

    public function testUserCreatedBannerScenario()
    {
        $expiresInMinutes = 45;

        $this->getRepository(ScenariosRepository::class)->createOrUpdate([
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'user_created'],
                    'elements' => ['element_banner']
                ])
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_banner',
                    'type' => ElementsRepository::ELEMENT_TYPE_BANNER,
                    'banner' => [
                        'id' => self::BANNER_ID,
                        'expiresInMinutes' => $expiresInMinutes,
                    ]
                ])
            ]
        ]);

        // Add user, which triggers scenario
        $user = $this->inject(UserManager::class)->addNewUser('test@email.com', false, 'unknown', null, false);

        // Mock client for Campaign API
        $container = [];
        $history = Middleware::history($container);
        $handler = HandlerStack::create(new MockHandler([
            new Response(202)
        ]));
        $handler->push($history);
        $client = new Client(['handler' => $handler]);
        $this->campaignApi->setClient($client);
        $now = new DateTime('2020-01-01 00:00:00');
        $this->campaignApi->setNow($now);

        // Run Hermes + Dispatcher
        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(true); // process trigger, finish its job and create banner job
        $this->engine->run(true); // banner job should be scheduled
        $this->dispatcher->handle(); // run banner job in Hermes
        $this->engine->run(true); // job should be deleted

        // Check banner request sent to Campaign API
        $this->assertCount(1, $container);

        /** @var Request $request */
        $request = $container[0]['request'];

        // Check URI Path
        $this->assertEquals($request->getUri()->getPath(), Api::showOneTimeBannerUriPath(self::BANNER_ID));

        // Check parameters
        $jsonParams = json_decode($request->getBody()->getContents());
        $this->assertEquals($user->id, $jsonParams->user_id);

        $expectedExpiresAt = $now->add(new \DateInterval("PT{$expiresInMinutes}M"));
        $this->assertEquals($expectedExpiresAt, new DateTime($jsonParams->expires_at));
    }
}

<?php

namespace Tests\Services;

use App\Repositories\UserRepository;
use App\Services\UserService;
use Mockery;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\UserFixture;

class UserServiceTest extends TestCase
{
    use UserFixture;

    private UserRepository $userRepository;
    private UserService $userService;
    private array $userData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userData = $this->getUserData();

        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userService = new UserService($this->userRepository);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->userData['is_active'] = 1;
        Mockery::close();
    }
}
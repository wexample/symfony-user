<?php

namespace Wexample\SymfonyUser\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wexample\SymfonyUser\Service\ActivationProgressService;
use Wexample\SymfonyUser\Service\TermsService;

class ActivationProgressServiceTest extends TestCase
{
    public function testNoStepsOutsideAnActivation(): void
    {
        $this->assertNull($this->service('1')->steps(ActivationProgressService::STEP_PASSWORD));
    }

    public function testTheStepsOfAnActivationEndingOnTheTerms(): void
    {
        $service = $this->service('1');
        $service->start();

        $this->assertSame(
            ['steps' => [['label' => 'password'], ['label' => 'code'], ['label' => 'terms']], 'current' => 1],
            $service->steps(ActivationProgressService::STEP_CODE)
        );

        // The last step shown ends it.
        $this->assertSame(2, $service->steps(ActivationProgressService::STEP_TERMS)['current']);
        $this->assertNull($service->steps(ActivationProgressService::STEP_CODE));
    }

    public function testWithoutTermsTheCodeIsTheLastStep(): void
    {
        $service = $this->service(null);
        $service->start();

        $this->assertCount(2, $service->steps(ActivationProgressService::STEP_PASSWORD)['steps']);
        $this->assertSame(1, $service->steps(ActivationProgressService::STEP_CODE)['current']);
        $this->assertNull($service->steps(ActivationProgressService::STEP_CODE));
    }

    private function service(?string $termsVersion): ActivationProgressService
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $key) => substr($key, strrpos($key, '.') + 1));

        $terms = $this->createStub(TermsService::class);
        $terms->method('getVersion')->willReturn($termsVersion);

        return new ActivationProgressService($requestStack, $translator, $terms);
    }
}

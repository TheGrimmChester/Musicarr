<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Http\ResponseFormatter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

class ResponseFormatterTest extends TestCase
{
    private ResponseFormatter $responseFormatter;
    private TranslatorInterface|MockObject $translator;

    protected function setUp(): void
    {
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->responseFormatter = new ResponseFormatter($this->translator);
    }

    public function testSuccessResponseWithDataAndMessage(): void
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $message = 'Success message';

        $this->translator->method('trans')->willReturn('Success');

        $response = $this->responseFormatter->successResponse($data, $message);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertTrue($content['success']);
        $this->assertEquals($message, $content['message']);
        $this->assertEquals($data, $content['data']);
    }

    public function testSuccessResponseWithDefaultMessage(): void
    {
        $data = ['id' => 1];

        $this->translator->method('trans')->willReturn('Default success message');

        $response = $this->responseFormatter->successResponse($data);

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Default success message', $content['message']);
    }

    public function testSuccessResponseWithCustomStatusCode(): void
    {
        $response = $this->responseFormatter->successResponse([], 'Created', 201);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testErrorResponse(): void
    {
        $message = 'Error message';
        $errors = ['field' => 'Invalid value'];
        $statusCode = 400;

        $response = $this->responseFormatter->errorResponse($message, $errors, $statusCode);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals($statusCode, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertFalse($content['success']);
        $this->assertEquals($message, $content['message']);
        $this->assertEquals($errors, $content['errors']);
    }

    public function testValidationErrorResponse(): void
    {
        $errors = ['field' => 'Invalid value'];
        $message = 'Validation failed';

        $this->translator->method('trans')->willReturn('Default validation message');

        $response = $this->responseFormatter->validationErrorResponse($errors, $message);

        $this->assertEquals(422, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals($message, $content['message']);
        $this->assertEquals($errors, $content['errors']);
    }

    public function testValidationErrorResponseWithDefaultMessage(): void
    {
        $errors = ['field' => 'Invalid value'];

        $this->translator->method('trans')->willReturn('Default validation message');

        $response = $this->responseFormatter->validationErrorResponse($errors);

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Default validation message', $content['message']);
    }

    public function testNotFoundResponse(): void
    {
        $message = 'Resource not found';

        $response = $this->responseFormatter->notFoundResponse($message);

        $this->assertEquals(404, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals($message, $content['message']);
    }

    public function testNotFoundResponseWithDefaultMessage(): void
    {
        $response = $this->responseFormatter->notFoundResponse();

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Resource not found', $content['message']);
    }

    public function testForbiddenResponse(): void
    {
        $message = 'Access denied';

        $response = $this->responseFormatter->forbiddenResponse($message);

        $this->assertEquals(403, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals($message, $content['message']);
    }

    public function testForbiddenResponseWithDefaultMessage(): void
    {
        $response = $this->responseFormatter->forbiddenResponse();

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Access denied', $content['message']);
    }

    public function testServerErrorResponse(): void
    {
        $message = 'Internal server error';

        $response = $this->responseFormatter->serverErrorResponse($message);

        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals($message, $content['message']);
    }

    public function testServerErrorResponseWithDefaultMessage(): void
    {
        $response = $this->responseFormatter->serverErrorResponse();

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Internal server error', $content['message']);
    }

    public function testPaginatedResponse(): void
    {
        $data = ['item1', 'item2'];
        $page = 2;
        $limit = 10;
        $total = 25;

        $response = $this->responseFormatter->paginatedResponse($data, $page, $limit, $total);

        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertTrue($content['success']);
        $this->assertEquals($data, $content['data']['items']);
        $this->assertEquals($page, $content['data']['pagination']['page']);
        $this->assertEquals($limit, $content['data']['pagination']['limit']);
        $this->assertEquals($total, $content['data']['pagination']['total']);
        $this->assertEquals(3, $content['data']['pagination']['pages']); // ceil(25/10)
        $this->assertTrue($content['data']['pagination']['hasNext']);
        $this->assertTrue($content['data']['pagination']['hasPrevious']);
    }

    public function testPaginatedResponseFirstPage(): void
    {
        $response = $this->responseFormatter->paginatedResponse(['item1'], 1, 10, 5);

        $content = json_decode($response->getContent(), true);
        $this->assertFalse($content['data']['pagination']['hasPrevious']);
        $this->assertFalse($content['data']['pagination']['hasNext']);
    }

    public function testPaginatedResponseLastPage(): void
    {
        $response = $this->responseFormatter->paginatedResponse(['item1'], 3, 10, 25);

        $content = json_decode($response->getContent(), true);
        $this->assertTrue($content['data']['pagination']['hasPrevious']);
        $this->assertFalse($content['data']['pagination']['hasNext']);
    }

    public function testListResponse(): void
    {
        $items = ['item1', 'item2'];
        $metadata = ['total' => 2, 'filtered' => 2];

        $response = $this->responseFormatter->listResponse($items, $metadata);

        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertTrue($content['success']);
        $this->assertEquals($items, $content['data']['items']);
        $this->assertEquals($metadata, $content['data']['metadata']);
    }

    public function testListResponseWithoutMetadata(): void
    {
        $items = ['item1'];

        $response = $this->responseFormatter->listResponse($items);

        $content = json_decode($response->getContent(), true);
        $this->assertEquals($items, $content['data']['items']);
        $this->assertEquals([], $content['data']['metadata']);
    }

    public function testItemResponse(): void
    {
        $item = ['id' => 1, 'name' => 'Test'];

        $response = $this->responseFormatter->itemResponse($item);

        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertTrue($content['success']);
        $this->assertEquals($item, $content['data']);
    }
}

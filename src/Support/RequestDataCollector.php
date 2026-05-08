<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Support;

use CodeIgniter\HTTP\IncomingRequest;
use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;

/**
 * Collect and normalize request input at the HTTP boundary.
 */
class RequestDataCollector
{
    /**
     * @param array<string, mixed>|null $params
     * @return array<string, mixed>
     */
    public function collect(IncomingRequest $request, ?array $params = null): array
    {
        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        $filesArray = $request->getFiles();
        $isMultipart = str_contains($contentType, 'multipart/form-data') || !empty($filesArray);

        $bodyPayload = [];
        if (!$isMultipart) {
            $rawBody = $request->getBody();
            $rawBodyString = is_string($rawBody) ? $rawBody : '';

            if ($rawBodyString !== '') {
                if (str_contains($contentType, 'json')) {
                    $decodedBody = json_decode($rawBodyString, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedBody)) {
                        $bodyPayload = $decodedBody;
                    } else {
                        throw new BadRequestException(lang('Api.invalidJsonPayload'));
                    }
                } else {
                    $bodyPayload = ['file' => $rawBodyString];
                }
            }
        }

        $rawInput = [];
        if (!$isMultipart && !str_contains($contentType, 'json')) {
            $rawInput = (array) $request->getRawInput();
        }

        $data = array_merge(
            (array) $request->getGet(),
            $rawInput,
            (array) $request->getPost(),
            $bodyPayload,
            $filesArray
        );

        return array_merge($data, $params ?? []);
    }
}

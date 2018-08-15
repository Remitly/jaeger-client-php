<?php

namespace Jaeger\Codec;

use Jaeger\SpanContext;

use const Jaeger\DEBUG_FLAG;
use const Jaeger\SAMPLED_FLAG;

use function Phlib\base_convert;

class ZipkinCodec implements CodecInterface
{
    const SAMPLED_NAME = 'X-B3-Sampled';
    const TRACE_ID_NAME = 'X-B3-TraceId';
    const SPAN_ID_NAME = 'X-B3-SpanId';
    const PARENT_ID_NAME = 'X-B3-ParentSpanId';
    const FLAGS_NAME = 'X-B3-Flags';

    /**
     * {@inheritdoc}
     *
     * @see \Jaeger\Tracer::inject
     *
     * @param SpanContext $spanContext
     * @param mixed $carrier
     *
     * @return void
     */
    public function inject(SpanContext $spanContext, &$carrier)
    {
        $carrier[self::TRACE_ID_NAME] = $spanContext->getTraceId();
        $carrier[self::SPAN_ID_NAME] = $spanContext->getSpanId();
        if ($spanContext->getParentId() != null) {
            $carrier[self::PARENT_ID_NAME] = $spanContext->getParentId();
        }
        $carrier[self::FLAGS_NAME] = (int) $spanContext->getFlags();
    }

    /**
     * {@inheritdoc}
     *
     * @see \Jaeger\Tracer::extract
     *
     * @param mixed $carrier
     * @return SpanContext|null
     */
    public function extract($carrier)
    {
        $flags = 0;

        if (isset($carrier[strtolower(self::SAMPLED_NAME)])) {
            if ($carrier[strtolower(self::SAMPLED_NAME)] === "1" ||
                strtolower($carrier[strtolower(self::SAMPLED_NAME)] === "true")
            ) {
                $flags = $flags | SAMPLED_FLAG;
            }
        }

        $traceId = self::extractHex(self::TRACE_ID_NAME, $carrier, 32) ?? "0";
        $spanId = self::extractHex(self::SPAN_ID_NAME, $carrier, 16) ?? "0";
        $parentId = self::extractHex(self::PARENT_ID_NAME, $carrier, 16) ?? "0";

        if (isset($carrier[strtolower(self::FLAGS_NAME)])) {
            if ($carrier[strtolower(self::FLAGS_NAME)] === "1") {
                $flags = $flags | DEBUG_FLAG;
            }
        }

        if ($traceId !== "0" && $spanId !== "0") {
            return new SpanContext($traceId, $spanId, $parentId, $flags);
        }

        return null;
    }

    private static function extractHex($key, $carrier, $maxlen)
    {
        $val = $carrier[strtolower($key)] ?? null;
        if (strlen($val) <= $maxlen && preg_match('/^[0-9a-fA-F]+$/', $val)) {
            return $val;
        }

        return null;
    }
}

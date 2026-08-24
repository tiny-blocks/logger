<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Internal\Redactor\MaskStyle;

/**
 * Rendering strategy for the hidden portion of a redacted value.
 *
 * <p>A proportional mask emits one character per hidden character, so the length of the original
 * value stays visible in the output. A fixed mask emits the same number of characters regardless of
 * the input, hiding the length as well.</p>
 */
final readonly class Mask
{
    private const int DEFAULT_LENGTH = 8;

    private function __construct(private MaskStyle $style, private string $fixedMask = '')
    {
    }

    /**
     * Creates a Mask that always emits the same number of mask characters.
     *
     * <p>The length of the original value is never revealed, and the mask is emitted even when
     * nothing was hidden, so a short value cannot be told apart from a long one.</p>
     *
     * @param int $length The number of mask characters emitted, eight when not given.
     * @return Mask The created instance.
     */
    public static function fixed(int $length = self::DEFAULT_LENGTH): Mask
    {
        return new Mask(style: MaskStyle::FIXED, fixedMask: MaskStyle::fixedMaskOf(length: $length));
    }

    /**
     * Creates a Mask that emits one mask character per hidden character.
     *
     * <p>The length of the hidden portion is revealed by the output.</p>
     *
     * @return Mask The created instance.
     */
    public static function proportional(): Mask
    {
        return new Mask(style: MaskStyle::PROPORTIONAL);
    }

    /**
     * Creates a Mask that hides letters and digits while keeping every other character.
     *
     * <p>Punctuation and spacing survive, so a formatted value keeps its shape.</p>
     *
     * @return Mask The created instance.
     */
    public static function preservingSeparators(): Mask
    {
        return new Mask(style: MaskStyle::PRESERVING_SEPARATORS);
    }

    /**
     * Renders the mask covering the given hidden portion of a value.
     *
     * @param string $hidden The portion of the original value that must not be shown.
     * @return string The mask replacing that portion.
     */
    public function applyTo(string $hidden): string
    {
        return $this->style->applyTo(hidden: $hidden, fixedMask: $this->fixedMask);
    }
}

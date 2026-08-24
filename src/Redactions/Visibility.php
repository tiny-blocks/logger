<?php

declare(strict_types=1);

namespace TinyBlocks\Logger\Redactions;

use TinyBlocks\Logger\Exceptions\NegativeVisibleLength;
use TinyBlocks\Logger\Internal\Redactor\VisibleShape;

/**
 * How much of a redacted value survives the mask.
 *
 * <p>The sibling decision of {@see Mask}, which renders the hidden portion. This one decides which
 * portion is hidden at all: nothing survives, the edges of the value survive, the edges of every
 * word survive, or everything before the at sign of an address is treated as the value.</p>
 */
final readonly class Visibility
{
    private function __construct(
        private VisibleShape $shape,
        private int $prefixLength,
        private int $suffixLength
    ) {
        if (min($prefixLength, $suffixLength) < 0) {
            $template = 'Visible length cannot be negative, got prefix %d and suffix %d.';

            throw new NegativeVisibleLength(message: sprintf($template, $prefixLength, $suffixLength));
        }
    }

    /**
     * Creates a Visibility where nothing of the value survives.
     *
     * @return Visibility The created instance.
     */
    public static function none(): Visibility
    {
        return new Visibility(shape: VisibleShape::EDGES, prefixLength: 0, suffixLength: 0);
    }

    /**
     * Creates a Visibility keeping a window at the head, at the tail, or at both ends of the value.
     *
     * @param int $prefixLength The number of leading characters left visible.
     * @param int $suffixLength The number of trailing characters left visible.
     * @return Visibility The created instance.
     * @throws NegativeVisibleLength If either length is negative.
     */
    public static function edges(int $prefixLength = 0, int $suffixLength = 0): Visibility
    {
        return new Visibility(
            shape: VisibleShape::EDGES,
            prefixLength: $prefixLength,
            suffixLength: $suffixLength
        );
    }

    /**
     * Creates a Visibility keeping the same window on every word of the value.
     *
     * <p>The value is split on whitespace and each word is masked on its own, so a multi word label
     * keeps its shape instead of collapsing into a single run of mask characters.</p>
     *
     * @param int $prefixLength The number of leading characters left visible in each word.
     * @param int $suffixLength The number of trailing characters left visible in each word.
     * @return Visibility The created instance.
     * @throws NegativeVisibleLength If either length is negative.
     */
    public static function words(int $prefixLength = 0, int $suffixLength = 0): Visibility
    {
        return new Visibility(
            shape: VisibleShape::WORDS,
            prefixLength: $prefixLength,
            suffixLength: $suffixLength
        );
    }

    /**
     * Creates a Visibility masking only what comes before the at sign, keeping the domain intact.
     *
     * <p>A value with no at sign is hidden whole, so a malformed address never leaks by falling
     * outside the rule.</p>
     *
     * @param int $prefixLength The number of leading characters of the local part left visible.
     * @return Visibility The created instance.
     * @throws NegativeVisibleLength If the length is negative.
     */
    public static function localPart(int $prefixLength): Visibility
    {
        return new Visibility(shape: VisibleShape::LOCAL_PART, prefixLength: $prefixLength, suffixLength: 0);
    }

    /**
     * Applies the mask to the portion of the value this visibility hides.
     *
     * @param Mask $mask The strategy rendering the hidden portion.
     * @param string $value The original value.
     * @return string The value with its hidden portion masked.
     */
    public function applyTo(Mask $mask, string $value): string
    {
        return $this->shape->applyTo(
            mask: $mask,
            value: $value,
            prefixLength: $this->prefixLength,
            suffixLength: $this->suffixLength
        );
    }
}

<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Package\Hector\Tests\Debug;

use Berlioz\Package\Hector\Debug\HectorSection;
use Hector\Connection\Bind\BindParam;
use Hector\Connection\Log\LogEntry;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

class HectorSectionTest extends TestCase
{
    public function testUnserializeInitializesLogger(): void
    {
        $section = new HectorSection();
        $unserializedSection = unserialize(serialize($section));

        $this->assertInstanceOf(HectorSection::class, $unserializedSection);

        $loggerProperty = new ReflectionProperty(HectorSection::class, 'logger');

        $this->assertTrue($loggerProperty->isInitialized($unserializedSection));
        $this->assertNull($loggerProperty->getValue($unserializedSection));
    }

    public function testInterpolateStatementReplacesNamedParameters(): void
    {
        $section = new HectorSection();
        $method = new ReflectionMethod(HectorSection::class, 'interpolateStatement');

        $statement = 'SELECT * FROM user WHERE id = :_h_0 AND name = :_h_1 AND active = :_h_2 AND deleted = :_h_3';
        $parameters = [
            new BindParam('_h_0', 42),
            new BindParam('_h_1', "O'Brien"),
            new BindParam('_h_2', true),
            new BindParam('_h_3', null),
        ];

        $result = $method->invoke($section, $statement, $parameters);

        $this->assertSame(
            "SELECT * FROM user WHERE id = 42 AND name = 'O''Brien' AND active = 1 AND deleted = NULL",
            $result,
        );
    }

    public function testInterpolateStatementReplacesPositionalParameters(): void
    {
        $section = new HectorSection();
        $method = new ReflectionMethod(HectorSection::class, 'interpolateStatement');

        $statement = 'SELECT * FROM user WHERE id = ? AND name = ?';
        $parameters = [
            new BindParam(1, 42),
            new BindParam(2, 'John'),
        ];

        $result = $method->invoke($section, $statement, $parameters);

        $this->assertSame("SELECT * FROM user WHERE id = 42 AND name = 'John'", $result);
    }

    public function testComputeDuplicatesCountsIdenticalQueries(): void
    {
        $logs = [
            new LogEntry('main', 'SELECT * FROM user WHERE id = ?', [new BindParam(1, 42)]),
            new LogEntry('main', 'SELECT * FROM user WHERE id = ?', [new BindParam(1, 99)]),
            new LogEntry('main', 'SELECT * FROM user WHERE id = ?', [new BindParam(1, 42)]),
            new LogEntry('main', 'SELECT * FROM article WHERE id = ?', [new BindParam(1, 42)]),
            new LogEntry('main', 'SELECT * FROM article WHERE id = ?', [new BindParam(1, 42)]),
        ];

        $section = $this->snapLogs($logs);

        // Logs 0 and 2 are identical (same statement and value) => executed twice.
        $this->assertSame(2, $section->getDuplicateCount(0));
        $this->assertSame(2, $section->getDuplicateCount(2));

        // Log 1 has a different value => unique.
        $this->assertNull($section->getDuplicateCount(1));

        // Logs 3 and 4 are identical => executed twice.
        $this->assertSame(2, $section->getDuplicateCount(3));
        $this->assertSame(2, $section->getDuplicateCount(4));

        // Two redundant executions in total (one per group of two).
        $this->assertSame(2, $section->countDuplicates());
    }

    public function testComputeDuplicatesDistinguishesConnections(): void
    {
        $logs = [
            new LogEntry('read', 'SELECT * FROM user WHERE id = ?', [new BindParam(1, 42)]),
            new LogEntry('write', 'SELECT * FROM user WHERE id = ?', [new BindParam(1, 42)]),
        ];

        $section = $this->snapLogs($logs);

        $this->assertNull($section->getDuplicateCount(0));
        $this->assertNull($section->getDuplicateCount(1));
        $this->assertSame(0, $section->countDuplicates());
    }

    public function testDuplicatesArePreservedThroughSerialization(): void
    {
        $logs = [
            new LogEntry('main', 'SELECT * FROM user WHERE id = ?', [new BindParam(1, 42)]),
            new LogEntry('main', 'SELECT * FROM user WHERE id = ?', [new BindParam(1, 42)]),
            new LogEntry('main', 'SELECT * FROM user WHERE id = ?', [new BindParam(1, 42)]),
        ];

        $section = $this->snapLogs($logs);
        /** @var HectorSection $unserialized */
        $unserialized = unserialize(serialize($section));

        $this->assertSame(3, $unserialized->getDuplicateCount(0));
        $this->assertSame(3, $unserialized->getDuplicateCount(2));
        $this->assertSame(2, $unserialized->countDuplicates());
    }

    public function testDuplicateThresholdControlsReporting(): void
    {
        $logs = [
            new LogEntry('main', 'SELECT * FROM user WHERE id = ?', [new BindParam(1, 42)]),
            new LogEntry('main', 'SELECT * FROM user WHERE id = ?', [new BindParam(1, 42)]),
        ];

        // With a threshold of 3, two identical queries are not reported.
        $section = $this->snapLogs($logs, 3);
        $this->assertNull($section->getDuplicateCount(0));
        $this->assertSame(0, $section->countDuplicates());

        // A third identical query crosses the threshold.
        $logs[] = new LogEntry('main', 'SELECT * FROM user WHERE id = ?', [new BindParam(1, 42)]);
        $section = $this->snapLogs($logs, 3);
        $this->assertSame(3, $section->getDuplicateCount(0));
        $this->assertSame(2, $section->countDuplicates());
    }

    public function testDuplicateThresholdNeverBelowTwo(): void
    {
        $logs = [
            new LogEntry('main', 'SELECT * FROM user WHERE id = ?', [new BindParam(1, 42)]),
            new LogEntry('main', 'SELECT * FROM user WHERE id = ?', [new BindParam(1, 42)]),
        ];

        // A threshold lower than 2 is clamped to 2.
        $section = $this->snapLogs($logs, 1);
        $this->assertSame(2, $section->getDuplicateCount(0));
    }

    public function testGetSeverityUsesAbsoluteThresholds(): void
    {
        $section = new HectorSection();
        $section->setThresholds(50, 100);

        $this->assertNull($section->getSeverity($this->entryWithDuration(0.02)));   // 20ms
        $this->assertNull($section->getSeverity($this->entryWithDuration(0.049)));  // 49ms
        $this->assertSame('warning', $section->getSeverity($this->entryWithDuration(0.05)));  // 50ms (inclusive)
        $this->assertSame('warning', $section->getSeverity($this->entryWithDuration(0.099))); // 99ms
        $this->assertSame('danger', $section->getSeverity($this->entryWithDuration(0.1)));    // 100ms (inclusive)
        $this->assertSame('danger', $section->getSeverity($this->entryWithDuration(0.5)));    // 500ms
    }

    public function testGetSeverityReturnsNullWhenDurationUnknown(): void
    {
        $section = new HectorSection();

        // Entry never ended => null duration.
        $this->assertNull($section->getSeverity(new LogEntry('main', 'SELECT 1')));
    }

    public function testThresholdsArePreservedThroughSerialization(): void
    {
        $section = new HectorSection();
        $section->setThresholds(25, 75)->setDuplicateThreshold(4);

        /** @var HectorSection $unserialized */
        $unserialized = unserialize(serialize($section));

        $this->assertSame(25.0, $unserialized->getSlowThreshold());
        $this->assertSame(75.0, $unserialized->getVerySlowThreshold());
        $this->assertSame('warning', $unserialized->getSeverity($this->entryWithDuration(0.03)));
    }

    /**
     * Build a log entry with a fixed duration.
     *
     * @param float $duration Duration in seconds.
     *
     * @return LogEntry
     */
    private function entryWithDuration(float $duration): LogEntry
    {
        $entry = new LogEntry('main', 'SELECT 1');

        $start = new ReflectionProperty(LogEntry::class, 'start');
        $end = new ReflectionProperty(LogEntry::class, 'end');
        $start->setValue($entry, 0.0);
        $end->setValue($entry, $duration);

        return $entry;
    }

    /**
     * Build a section and run the duplicate detection on the given log entries.
     *
     * @param LogEntry[] $logs
     * @param int $duplicateThreshold
     *
     * @return HectorSection
     */
    private function snapLogs(array $logs, int $duplicateThreshold = 2): HectorSection
    {
        $section = new HectorSection();
        $section->setDuplicateThreshold($duplicateThreshold);

        // computeDuplicates() populates both the per-index counts and the redundant total.
        (new ReflectionMethod(HectorSection::class, 'computeDuplicates'))->invoke($section, $logs);

        return $section;
    }
}

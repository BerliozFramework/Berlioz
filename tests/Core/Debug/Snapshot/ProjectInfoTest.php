<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Core\Tests\Debug\Snapshot;

use Berlioz\Core\Composer\Composer;
use Berlioz\Core\Debug\Snapshot\ProjectInfo;
use PHPUnit\Framework\TestCase;

class ProjectInfoTest extends TestCase
{
    public function test()
    {
        $projectInfo = new ProjectInfo(new Composer($composerName = 'fake'));

        $declared = get_declared_classes();
        $included = get_included_files();

        $this->assertEquals($declared, $projectInfo->getDeclaredClasses());
        $this->assertEquals($included, $projectInfo->getIncludedFiles());
        $this->assertEquals($composerName, $projectInfo->getComposer()->getName());
    }
}

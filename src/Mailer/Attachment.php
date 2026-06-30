<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2017 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Mailer;

use Berlioz\Mailer\Exception\MailerException;

/**
 * Class Attachment.
 */
class Attachment
{
    /** @var string Id */
    private $id;
    /** @var string Type */
    private $type;
    /** @var string Name */
    private $name;
    /** @var string Disposition */
    private $disposition;
    /** @var string File name */
    private $fileName;
    /** @var ?string Contents */
    private $_contents;

    /**
     * Attachment constructor.
     *
     * @param string $fileName
     */
    public function __construct(string $fileName)
    {
        if (is_file($fileName)) {
            $this->fileName = $fileName;
            $this->id = null;
            $this->name = basename($fileName);
            $this->disposition = 'attachment';
        }
    }

    public function __serialize(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->getType(),
            'name' => $this->name,
            'disposition' => $this->disposition,
            'fileName' => $this->fileName,
            'contents' => $this->getContents(),
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->id = $data['id'];
        $this->type = $data['type'];
        $this->name = $data['name'];
        $this->disposition = $data['disposition'];
        $this->fileName = $data['fileName'];
        $this->_contents = $data['contents'];
    }

    /**
     * Get content ID.
     *
     * If you call this method, it's only for get id for HTML insertion of attachments.
     * Else, this attachment will do not appear in downloadable attachments of mail.
     *
     * @param string|null $domainName
     *
     * @return string
     */
    public function getId(?string $domainName = null): string
    {
        if (null !== $this->id) {
            return $this->id;
        }

        $source = "0123456789";
        $n = strlen($source);

        // Construct content id
        $id = "part1.";
        for ($i = 0; $i < 8; $i++) {
            $id .= $source[mt_rand(1, $n) - 1];
        }

        $id .= ".";
        for ($i = 0; $i < 8; $i++) {
            $id .= $source[mt_rand(1, $n) - 1];
        }

        $id .= "@" . ($domainName ?? "berlioz");

        $this->id = $id;

        return $this->id;
    }

    /**
     * If has content ID.
     *
     * @return bool
     */
    public function hasId(): bool
    {
        return null !== $this->id;
    }

    /**
     * Get type.
     *
     * @return string
     * @throws MailerException
     */
    public function getType(): string
    {
        if (null !== $this->type) {
            return $this->type;
        }

        if (!file_exists($this->fileName)) {
            throw new MailerException(sprintf('Attachment file "%s" does not exists', $this->fileName));
        }

        $finfo = finfo_open(FILEINFO_MIME);
        $mime = finfo_file($finfo, $this->fileName);

        $mime = explode(";", $mime);
        $mime = trim($mime[0]);
        $this->type = $mime;

        return $this->type;
    }

    /**
     * Get name.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return static
     */
    public function setName(string $name): Attachment
    {
        $this->name = basename($name);

        return $this;
    }

    /**
     * Get disposition.
     *
     * @return string|null
     */
    public function getDisposition(): ?string
    {
        return $this->disposition;
    }

    /**
     * Set disposition.
     *
     * @param string $disposition
     *
     * @return static
     */
    public function setDisposition(string $disposition): Attachment
    {
        $this->disposition = $disposition;

        return $this;
    }

    /**
     * Get contents.
     *
     * @return false|string
     */
    public function getContents()
    {
        if (null !== $this->_contents) {
            return $this->_contents;
        }

        return file_get_contents($this->fileName);
    }
}
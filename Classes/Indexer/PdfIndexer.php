<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Indexer;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use RuntimeException;
use TYPO3\CMS\Core\Core\Environment;

class PdfIndexer extends AbstractIndexer
{
    protected string $content = '';
    protected int $pid;

    public function process(): void
    {
        $this->pingTika();
        $client = $this->config->getClient();
        $files = $this->extractFilesFromDomByFileExtension('pdf', $this->content);
        $files = $this->deduplicateFiles($files);
        $i = 0;
        $params = [];
        foreach ($files as $file) {
            $fileContent = $this->getFileContent($file['href']);
            $content = $this->extractViaTika($fileContent);
            $id = $this->tableName . '/' . sha1($file['href']);

            $params['body'][] = $this->getIndexBody($id);
            $documentBody = $this->getDocumentBody(array_merge($content, $file, ['pid' => $this->pid]));
            $params['body'][] = $documentBody;

            $i++;
            if ($i > 10) {
                $client->bulk($params);
                $params = [];
            }
        }

        if ($params) {
            $client->bulk($params);
        }
    }

    /**
     * @see https://cwiki.apache.org/confluence/display/TIKA/TikaServer#TikaServer-UsingprebuiltDockerimage
     */
    protected function pingTika(): bool
    {
        $version = $this->call($this->getTikaUri() . 'version', 'GET', [], null);
        if (strpos($version, 'Apache Tika') !== 0) {
            $this->output->writeln('Could not connect to tika (' . $this->getTikaUri() . 'version' . '), received: ' . $version);
            return false;
        }
        return true;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function call(string $uri, string $method = 'GET', $headers = [], $body = null): string
    {
        $request = new Request($method, $uri, $headers, $body);
        $response = (new Client())->send($request);

        return $response->getBody()->getContents();
    }

    protected function getTikaUri(): string
    {
        $tikaUri = getenv('TIKA_URI');
        if (!$tikaUri) {
            $this->output->writeln('.env TIKA_URI is empty, can not index files');
            return '';
        }
        if ($tikaUri[strlen($tikaUri) - 1] !== '/') {
            $tikaUri .= '/';
        }
        return $tikaUri;
    }

    /**
     * @param string $fileExtension
     * @param string $html
     * @return array<string, string>
     */
    protected function extractFilesFromDomByFileExtension(string $fileExtension, string $html): array
    {
        $files = [];
        if (array_flip(get_loaded_extensions())['dom'] ?? false) {
            $doc = new \DOMDocument();
            @$doc->loadHTML($html);

            $anchors = $doc->getElementsByTagName('a');
            /** @var \DOMElement $anchor */
            foreach ($anchors as $anchor) {
                $href = $anchor->getAttribute('href');
                if (strrpos($href, '.' . $fileExtension) !== false) {
                    $title = trim(strip_tags($anchor->textContent));
                    if (!$title) {
                        $title = $href;
                    }
                    $title = trim($title);
                    $files[] = [
                        'href' => $href,
                        'title' => $title,
                    ];
                }
            }
            return $files;
        }

        $this->output->writeln('Could not parse anchors on html, did not investigate PDF contents');
        return [];
    }

    /**
     * The files may be multiple times in our html - linked image with linked file afterwards
     * also one time there will be a text-node to extract the title, another time not.
     * find the best result and all pdfs once anyway
     *
     * @param array $files
     * @return array
     */
    protected function deduplicateFiles(array $files): array
    {
        $deduplicatedFiles = [];
        foreach ($files as $file) {
            foreach ($deduplicatedFiles as &$deduplicatedFile) {
                if ($file['href'] === $deduplicatedFile['href']) {
                    if ($deduplicatedFile['title'] === $deduplicatedFile['href']) {
                        $deduplicatedFile['title'] = $file['title'];
                    }
                    continue 2;
                }
            }
            unset($deduplicatedFile);
            $deduplicatedFiles[] = $file;
        }

        return $deduplicatedFiles;
    }

    protected function getFileContent(string $file): string
    {
        if ($file[0] === '/') {
            $fileName = Environment::getPublicPath() . $file;
            if (!file_exists($fileName)) {
                throw new RuntimeException('File not found ' . $fileName);
            }
            return file_get_contents($fileName);
        }

        return $this->call($file);
    }

    protected function extractViaTika(string $fileContent): array
    {
        $return = [];
        foreach (['meta' => 'Accept: application/json', 'tika' => 'Accept: text/plain'] as $endpoint => $returnType) {
            $contentFromTika = $this->call(
                $this->getTikaUri() . $endpoint,
                'PUT',
                [
                    'Content-type: application/pdf',
                    $returnType,
                ],
                $fileContent
            );

            if ($contentFromTika) {
                if ($endpoint === 'meta') {
                    $contentFromTika = json_decode($contentFromTika, true);
                }

                if (false === $contentFromTika) {
                    $this->output->writeln('Could not decode content(' . $endpoint . ') from tika' . substr($fileContent, 0, 32));
                    continue;
                }
                $return[$endpoint] = $contentFromTika;
            }
        }

        return $return;
    }

    public function setPid(int $pid): self
    {
        $this->pid = $pid;
        return $this;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

}

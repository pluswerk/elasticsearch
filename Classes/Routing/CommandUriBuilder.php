<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Routing;

use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class CommandUriBuilder extends UriBuilder
{
    /**
     * Creates an URI used for linking to an Extbase action.
     * Works in Frontend and Backend mode of TYPO3.
     *
     * @param string $actionName Name of the action to be called
     * @param array $controllerArguments Additional query parameters. Will be "namespaced" and merged with $this->arguments.
     * @param string $controllerName Name of the target controller. If not set, current ControllerName is used.
     * @param string $extensionName Name of the target extension, without underscores. If not set, current ExtensionName is used.
     * @param string $pluginName Name of the target plugin. If not set, current PluginName is used.
     * @return string the rendered URI
     * @see build()
     */
    public function uriFor($actionName = null, $controllerArguments = [], $controllerName = null, $extensionName = null, $pluginName = null): string
    {
        if ($actionName !== null) {
            $controllerArguments['action'] = $actionName;
        }
        if ($controllerName !== null) {
            $controllerArguments['controller'] = $controllerName;
        } else {
            $controllerArguments['controller'] = $this->request->getControllerName();
        }
        if ($extensionName === null) {
            $extensionName = $this->request->getControllerExtensionName();
        }
        if ($pluginName === null && $this->environmentService->isEnvironmentInFrontendMode()) {
            $pluginName = $this->extensionService->getPluginNameByAction($extensionName, $controllerArguments['controller'], $controllerArguments['action']);
        }
        if ($pluginName === null) {
            $pluginName = $this->request->getPluginName();
        }
        if ($this->environmentService->isEnvironmentInFrontendMode() && $this->configurationManager->isFeatureEnabled('skipDefaultArguments')) {
            $controllerArguments = $this->removeDefaultControllerAndAction($controllerArguments, $extensionName, $pluginName);
        }
        if ($this->targetPageUid === null && $this->environmentService->isEnvironmentInFrontendMode()) {
            $this->targetPageUid = $this->extensionService->getTargetPidByPlugin($extensionName, $pluginName);
        }
        if ($this->format !== '') {
            $controllerArguments['format'] = $this->format;
        }
        if ($this->argumentPrefix !== null) {
            $prefixedControllerArguments = [$this->argumentPrefix => $controllerArguments];
        } else {
            $pluginNamespace = $this->extensionService->getPluginNamespace($extensionName, $pluginName);
            $prefixedControllerArguments = [$pluginNamespace => $controllerArguments];
        }
        ArrayUtility::mergeRecursiveWithOverrule($this->arguments, $prefixedControllerArguments);
        return $this->buildFrontendUri();
    }

    /**
     * Builds the URI, frontend flavour
     *
     * @return string The URI
     * @see buildTypolinkConfiguration()
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function buildFrontendUri(): string
    {
        $this->contentObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        return parent::buildFrontendUri();
    }
}

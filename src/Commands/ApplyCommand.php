<?php

namespace Pantheon\TerminusMassUpdate\Commands;

// @TODO: Autoloading.
require_once "MassUpdateCommandBase.php";

class ApplyCommand extends MassUpdateCommandBase
{
    protected $command = 'site:mass-update:apply';

    /**
     * Apply all available upstream updates to all sites.
     *
     * @authorize
     *
     * @command site:mass-update:apply
     * @aliases mass-update
     *
     * @param array $options
     * @return RowsOfFields
     * 
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     * @option upstream Update only sites using the given upstream
     * @option boolean $updatedb Run update.php after updating (Drupal only)
     * @option boolean $accept-upstream Attempt to automatically resolve conflicts in favor of the upstream
     * @option dry-run Don't actually apply the updates
     */
    public function applyAllUpdates($options = ['upstream' => '', 'updatedb' => false, 'accept-upstream' => false, 'dry-run' => false])
    {
        $site_updates = $this->getAllSitesAndUpdates($options);
        foreach ($site_updates as $info) {
            $site = $info['site'];
            $updates = $info['updates'];

            $env = $site->getEnvironments()->get('dev');

            if ($env->get('connection_mode') !== 'git') {
                $this->log()->warning(
                    'Cannot apply updates to {site} because the dev environment is not in git mode.',
                    ['site' => $site->getName()]
                );
            }
            else {
                $logname = $options['dry-run'] ? 'DRY RUN' : 'notice';
                $this->log()->notice(
                    'Applying {updates} updates to {site}',
                    ['site' => $site->getName(), 'updates' => count($updates), 'name' => $logname]);

                // Do the actual updates if we're not in dry-run mode
                if (!$options['dry-run']) {
                    // @TODO: We may be able to run workflows asynchronously to save time.
                    $workflow = $env->applyUpstreamUpdates(
                        isset($options['updatedb']) ? $options['updatedb'] : false,
                        isset($options['accept-upstream']) ? $options['accept-upstream'] : false
                    );
                    while (!$workflow->checkProgress()) {
                        // @TODO: Add Symfony progress bar to indicate that something is happening.
                    }
                    $this->log()->notice($workflow->getMessage());
                }
            }
        }
    }
}

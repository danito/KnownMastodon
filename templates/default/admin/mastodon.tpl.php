<div class = "row">
    <div class="col-md-10 col-md-offset-1">
        <?= $this->draw('admin/menu') ?>
        <h1><?= \Idno\Core\Idno::site()->language()->_('Syndicate to Mastodon') ?></h1>
    </div>
</div>

<div class="row">
    <div class="col-md-10 col-md-offset-1">
        <p><?= \Idno\Core\Idno::site()->language()->_('Your Mastodon plugin is installed. For configuration check your account settings.') ?></p>

    </div>
</div>
<div class="row">
    <div class="col-md-10 col-md-offset-1">
        <?php
        if (!empty(\Idno\Core\Idno::site()->config()->mastodon)) {
            ?>
            <p><?= \Idno\Core\Idno::site()->language()->_('Following Mastodon servers are configured:') ?></p>
            <?php
            $servers = \Idno\Core\Idno::site()->config()->mastodon;
            foreach ($servers as $servername => $details) {
                \Idno\Core\Idno::site()->logging()->log(\Idno\Core\Idno::site()->language()->_('Mastodon (admin details): %s', [var_export($details, true)]));
                if (!empty($details[0]['name'])) {
                    $name = $details[0]["name"];
                    ?>
                    <form action=<?= \Idno\Core\Idno::site()->config()->getDisplayURL() ?>admin/mastodon/ method=post >
                    <div class="col-md-9 panel panel-default">
                        <div class="panel-heading row">
                            <div class="col-md-9"><?= \Idno\Core\Idno::site()->language()->_('Server : %s', ['<a href="https://' . $name . '" target="_blank">' . $name . '</a>']) ?></div>
                            <div class="col-md-1 col-md-offset-1">
                              <input class="btn btn-cancel plugin-button" name="_remove" type="submit" value="<?= \Idno\Core\Idno::site()->language()->_('Delete') ?>">
                            </div>
                            <input type="hidden" name="remove" value="<?= $name ?>"/>
                        </div>
                        <?= \Idno\Core\Idno::site()->actions()->signForm('/admin/mastodon/') ?>
                        <div class="panel-body" >
                            <p>
                                 <?= \Idno\Core\Idno::site()->language()->_('Authorized <strong>%s</strong> by <strong>%s</strong>.', [strftime('%Y-%m-%d', $details[0]['issued_at']), $details[0]['user']]) ?>
                            </p>
                            <p>
                                 <?= \Idno\Core\Idno::site()->language()->_('ID: %s&hellip;', [substr($details[0]['client_id'], 0, 5)]) ?>
                            </p>

                        </div>
                    </div>
                    </form>
                    <?php
                }
            }

?>
<div class="row">
    <div class="col-md-9 col-md-offset-0">

    <?php echo $this->__([])->draw('forms/usersmastodon'); ?>

    </div>
</div>
<?php

        } else {
            ?>
            <div class="panel panel-default">
                <div class="panel-heading">
                     <?= \Idno\Core\Idno::site()->language()->_('Server : none') ?>
                </div>
                <div class="panel-body" >
                    <p>
                         <?= \Idno\Core\Idno::site()->language()->_('Authorized <strong>n/a</strong> by <strong>n/a</strong>.') ?>
                    </p>
                    <p>
                        <?= \Idno\Core\Idno::site()->language()->_('ID: n/a') ?>
                    </p>

                </div>
            </div>
            <?php
        }
        ?>
    </div>
</div>

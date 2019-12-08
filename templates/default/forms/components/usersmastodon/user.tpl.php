<?php

$user = $vars['user'];

if ($user instanceof \Idno\Entities\User) {
    $handle = $user->getHandle();
    if (!empty($handle)) {
        if (strlen($handle) > 18) {
            $display_handle = substr($handle, 0, 16) . '...';
        } else {
            $display_handle = $handle;
        }
        /* @var \Idno\Entities\User $user */
        ?>

    <div class="row <?php echo strtolower(str_replace('\\', '-', get_class($user))); ?>">
        <div class="col-sm-5 col-xs-12">
        <p class="user-tbl">
            <img src="<?php echo $user->getIcon() ?>">
            <a href="<?php echo $user->getDisplayURL() ?>"><?php echo htmlentities($user->getTitle()) ?></a>
            (<a href="<?php echo $user->getDisplayURL() ?>"><?php echo $display_handle ?></a>)<br>
            <small><?php echo $user->email ?></small>
        </p>
        </div>
        <div class="col-sm-2 col-xs-6">
        <p class="user-tbl">
            <?php

              if ( !empty($user->mastodon) ) {

                  foreach ($user->mastodon as $k => $v) {
                      ?><small><?php
                          echo $k;
                      ?></small><br /><?php
                  }
              }

            ?>
        </p>
        </div>
        <div class="col-sm-1 col-xs-6">
        </div>
        <div class="col-sm-3 col-xs-6">
        <p class="user-tbl">
            <small><strong><?php echo \Idno\Core\Idno::site()->language()->_('Last update posted'); ?></strong>
            <br>
            <?php
            $feed = \Idno\Common\Entity::getFromX(null, ['owner' => $user->getUUID()], array(), 1, 0);
            if (!empty($feed) && is_array($feed)) {
                ?>
                <time datetime="<?php echo date('r', $feed[0]->updated) ?>"
                      class="dt-published"><?php echo date('r', $feed[0]->updated) ?></time>
            <?php } else {
                ?>
                <?php echo \Idno\Core\Idno::site()->language()->_('Never'); ?>
            <?php }
?>
            </small>
        </p>
        </div>
    </div>

        <?php
    }
}

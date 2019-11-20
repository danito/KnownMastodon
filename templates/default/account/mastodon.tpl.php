<?php

use Idno\Core\Idno;

$baseURL = Idno::site()->config()->getDisplayURL();
$user = Idno::site()->session()->currentUser();
?>


<div class="col-md-offset-1 col-md-10">
    <?= $this->draw('account/menu') ?>
    <h1>Mastodon</h1>
    <?php
    if (empty($user->mastodon)) {
        ?>
        <p>1. Enter your Mastodon instance’s user details:</p>
        <form action="<?= $baseURL ?>account/mastodon/" class="form-horizontal" method="post">
            <label for="login">Mastodon login (email address)</label>
            <input type="email" class="form-control" name="login" id="login" placeholder="your@email.net" value="" />
            <label for="username">Mastodon full username</label>
            <input type="email" class="form-control" name="username" id="username" placeholder="yourNick@mastodon.social" value="" />
            <button type="submit" class="btn btn-primary">Save</button>
            <?= \Idno\Core\Idno::site()->actions()->signForm('/account/mastodon/') ?>
        </form>
        <?php
    } elseif(isset($_SESSION['mastodon_instance'])) {
      if (!empty($user->mastodon[$_SESSION['mastodon_instance']]['username']) && empty($user->mastodon[$_SESSION['mastodon_instance']]['bearer'])) {
        $account = $user->mastodon[$_SESSION['mastodon_instance']];
        $server = $account['server'];
        $config = \Idno\Core\Idno::site()->config()->config['mastodon'][$server];
        $authUrl = urldecode(\Idno\Core\Idno::site()->config()->config['mastodon'][$server][0]['auth_url']);
        ?>
        <p>2. Authorize with <?= $server ?></p>
        <div class="control-group">
            <div class="controls-config">
                <div class="row">
                    <div class="col-md-7">
                        <p>
                            Your account is currently connected to Mastodon. Public updates, pictures, and posts
                            that you publish here
                            can be cross-posted to <?= $server ?>.
                        </p>

                        <div class="social">
                            <form action="<?= $authUrl ?>"
                                  class="form-horizontal" method="post">
                                <input type="text" class="form-control disabled" name="login" id="login" placeholder="your@email.net" disabled="disabled" value="<?= $account['login'] ?>" />
                                <label for="username">Mastodon full username</label>
                                <input type="text" class="form-control disabled" name="username" disabled="disabled" id="username" placeholder="yourNick@mastodon.social" value="<?= $account['username'] . '@' . $server ?>" />
                                <button type="submit" class="btn btn-primary" disabled="disabled">Save</button>

                                <p>
                                    <input type="hidden" name="remove" value="1"/>
                                    <a href="<?= $authUrl ?>" class="btn btn-primary">Connect to <?= $server ?></a>

                                    <?= \Idno\Core\Idno::site()->actions()->signForm('/account/mastodon/') ?>
                                </p>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php
      }
    } else {
        ?>
        <div class="control-group">
            <div class="controls-config"><?php

?>
                <div class="row">
                    <div class="col-md-7">
                        <p>
                            Your account is currently connected to these Mastodon instances.
                            Public updates, pictures, and posts that you publish here can be cross-posted.
                        </p>

                            <form action="<?= \Idno\Core\Idno::site()->config()->getDisplayURL() ?>account/mastodon/"
                                  class="form-horizontal" method="post">
                        <?php
                           if($accounts = \Idno\Core\Idno::site()->syndication()->getServiceAccounts('mastodon')) {

                             foreach($accounts as $account) {
                               $tmp = explode('@', $account['username']);
                        ?>
                        <div class="social">
                                <p>
                                    <input type="hidden" name="remove" value="<?= $account['username'] ?>"/>
                                    <button type="submit" class="connect mastodon connected"><i class="fa fa-user-circle"></i>
                                        Disconnect <?= $tmp[1] ?>
                                    </button>
                                    <?= \Idno\Core\Idno::site()->actions()->signForm('/account/mastodon/') ?>
                                </p>
                        </div>
                        <?php
                             }
                           }
        ?>
                            </form>
        <p>1. Enter your Mastodon instance’s user details:</p>
        <form action="<?= $baseURL ?>account/mastodon/" class="form-horizontal" method="post">
            <label for="login">Mastodon login (email address)</label>
            <input type="email" class="form-control" name="login" id="login" placeholder="your@email.net" value="" />
            <label for="username">Mastodon full username</label>
            <input type="email" class="form-control" name="username" id="username" placeholder="yourNick@mastodon.social" value="" />
            <button type="submit" class="btn btn-primary">Save</button>
            <?= \Idno\Core\Idno::site()->actions()->signForm('/account/mastodon/') ?>
        </form>
        <?php
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        \Idno\Core\Idno::site()->logging()->log("Mastodon debug : " . var_export($account, true));
    }
    ?>

</div>

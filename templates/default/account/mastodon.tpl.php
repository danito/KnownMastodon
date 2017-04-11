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
        <p>1. enter your Mastodon's user details:</p>
        <form action="<?= $baseURL ?>account/mastodon/" class="form-horizontal" method="post">
            <label for="login">Mastodon login (email address)</label>
            <input type="text" class="form-control" name="login" id="login" placeholder="your@email.net" value="" />
            <label for="username">Mastodon full username</label>
            <input type="text" class="form-control" name="username" id="username" placeholder="yourNick@mastodon.social" value="" />
            <button type="submit" class="btn btn-primary">Save</button>
            <?= \Idno\Core\site()->actions()->signForm('/account/mastodon/') ?>
        </form>
        <?php
    } elseif (!empty($user->mastodon['username']) && empty($user->mastodon['bearer'])) {
        $account = $user->mastodon;
        $server = $account['server'];
        $authUrl = Idno::site()->config()->mastodon[$server]['auth_url'];
        ?>
        <p>Authorize with <?= $server?></p>
        <form class="form-horizontal" method="post">
            <label for="login">Mastodon login (email address)</label>
            <input type="text" class="form-control disabled" name="login" id="login" placeholder="your@email.net" disabled="disabled" value="<?= $account['login'] ?>" />
            <label for="username">Mastodon full username</label>
            <input type="text" class="form-control disabled" name="username" disabled="disabled" id="username" placeholder="yourNick@mastodon.social" value="<?= $account['username'] ?>" />
            <button type="submit" class="btn btn-primary" disabled="disabled">Save</button>
        </form>
        <div class="control-group">
            <div class="controls-config">
                <div class="row">
                    <div class="col-md-7">
                         <div class="social">

                            <p>
                                <a href="<?= $authUrl ?>" class="connect mastodon"><i class="fa fa-user-circle"></i>
                                    Connect <?= $server?></a>
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        <?php
    } else {
        $account = $user->mastodon;
        $server = $account['server'];
        ?>
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
                                    <form action="<?= \Idno\Core\site()->config()->getDisplayURL() ?>account/mastodon/"
                                          class="form-horizontal" method="post">
                                        <p>
                                            <input type="hidden" name="remove" value="1"/>
                                            <button type="submit" class="connect mastodon connected"><i class="fa fa-user-circle"></i>
 Disconnect <?= $server ?>
                                            </button>
                                            <?= \Idno\Core\site()->actions()->signForm('/account/mastodon/') ?>
                                        </p>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        <?php
    }
    ?>
</div>    
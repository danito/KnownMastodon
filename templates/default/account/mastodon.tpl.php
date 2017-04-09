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
            <label for="account_id">Mastodon login (email address)</label>
            <input type="text" class="form-control" name="account_id" id="account_id" placeholder="" value="<?= $account ? $account : '' ?>" />
            <label for="account_id">Mastodon full username</label>
            <input type="text" class="form-control" name="account_id" id="account_id" placeholder="yourNick@mastodon.social" value="<?= $account ? $account : '' ?>" />
            <button type="submit" class="btn btn-primary">Save</button>
        <?= \Idno\Core\site()->actions()->signForm('/account/mastodon/') ?>
        </form>
        <?php
    }
    ?>
</div>    
<?php
/**
 * @author xzit.online <hallo@xzit.email>
 * @website https://github.com/basteyy
 * @website https://xzit.online
 */

declare(strict_types=1);
$this->layout('layout', ['title' => 'Examples']);

?>
<main class="container-xxl">

    <h1 class="mt-md-5">PlatesUrlToolset</h1>

    <p class="lead">
        This is a simple example of the PlatesUrlToolset.
    </p>

    <table class="table table-striped">
        <thead>
        <tr>
            <th scope="col">Var</th>
            <th scope="col">Value</th>
            <th scope="col">Result</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>Get current URL</td>
            <td><code>$this->getCurrentUrl()</code></td>
            <td><?= $this->getCurrentUrl() ?></td>
        </tr>
        <tr>
            <td>Create an link (a-tag)</td>
            <td><code>$this->getLink('/foo', 'Click it!!', 'Yes, click me', 'btn btn-primary', false);</code></td>
            <td><?= $this->getLink('/foo', 'Click it!!', 'Yes, click me', 'btn btn-primary', false); ?></td>
        </tr>
        <tr>
            <td>Get named link</td>
            <td><code>$this->getNamedLink('home', 'Link to home', 'Yes, click me') </code></td>
            <td><?= $this->getNamedLink('home', 'Link to home', 'Yes, click me') ?></td>
        </tr>
        <tr>
            <td>Get names url</td>
            <td><code>$this->getNamedUrl('home')</code></td>
            <td><?= $this->getNamedUrl('home') ?></td>
        </tr>
        <tr>
            <td>Appended debugging timestamp</td>
            <td><code>$this->getDebugUrl('/foobar/file/something.css');</code></td>
            <td><?= $this->getDebugUrl('/foobar/file/something.css'); ?></td>
        </tr>
        </tbody>
    </table>


</main>
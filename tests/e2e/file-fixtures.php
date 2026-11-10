<?php

declare(strict_types=1);

use CraftCms\Cms\Asset\Data\Volume;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Folders;
use CraftCms\Cms\Support\Facades\Volumes;
use CraftCms\Cms\User\Elements\User as UserElement;
use CraftCms\Cms\User\UserPermissions;

/** Run only behind fixtures.php's isolated-database guard. */
function seedFileFixtures(): array
{
    $disk = ['driver' => 'local', 'root' => storage_path('app/e2e-private-files'), 'visibility' => 'private', 'throw' => true];
    $config = config('filesystems');
    $config['disks']['privateFiles'] = $disk;
    config(['filesystems' => $config]);
    file_put_contents(config_path('filesystems.php'), '<?php return '.var_export($config, true).";\n");
    file_put_contents(config_path('site-files.php'), "<?php return ['volume' => 'privateFiles'];\n");
    $volume = Volumes::getVolumeByHandle('privateFiles') ?? new Volume([
        'name' => 'Private test files', 'handle' => 'privateFiles', 'fsHandle' => 'privateFiles', 'hasUrls' => false,
    ]);
    save($volume, Volumes::saveVolume($volume));
    $credentials = json_decode(file_get_contents(__DIR__.'/.runtime/credentials.json'), true, flags: JSON_THROW_ON_ERROR);
    $users = [];
    foreach (['denied' => [], 'uploader' => ['saveAssets:'.$volume->uid, 'viewAssets:'.$volume->uid], 'reader' => ['viewAssets:'.$volume->uid, 'viewPeerAssets:'.$volume->uid], 'invalidated' => ['saveAssets:'.$volume->uid, 'viewAssets:'.$volume->uid], 'throttled' => ['saveAssets:'.$volume->uid, 'viewAssets:'.$volume->uid]] as $role => $permissions) {
        $user = UserElement::find()->username('file-'.$role)->status(null)->one() ?? new UserElement([
            'username' => 'file-'.$role, 'email' => $role.'@inertia-poc.test',
            'newPassword' => $credentials['password'], 'active' => true,
        ]);
        save($user, Elements::saveElement($user));
        app(UserPermissions::class)->saveUserPermissions($user->id, $permissions);
        $users[$role] = ['username' => $user->username];
    }

    return ['folderId' => Folders::getRootFolderByVolumeId($volume->id)->id, 'volumeId' => $volume->id, 'users' => $users];
}

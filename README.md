# RedcapProvisioner

access the running comanage services container

copy a vaguely similar plugin from 
```./app/AvailablePlugin/```
to
```./local/Plugin/RedcapProvisioner```

in our example, copying: ```ApiProvisioner```

From within the ```./local/Plugin/RedcapProvisioner```
directory, review naming conventions in the following files:

```./Config/Schema/schema.xml```
specifically ```table``` and ```index``` names 

Rename 
```./Controller/ApiProvisionerAppController.php```
to
```./Controller/RedcapProvisionerAppController.php```

Update ```RedcapProvisionerAppController.php```
adjusting the ```class```

Rename
```./Model/ApiProvisioner.php```
To
```./Model/RedcapProvisionerAppModel.php```

Update ```RedcapProvisionerAppModel.php```
adjusting the ```class```

remove other php files, but keep the existing file structure.

clear caches and then update your database schema using 
```
$ cd $REGISTRY/app
$ su -c "./Console/clearcache" ${APACHE_USER}
$ su -c "./Console/cake database" ${APACHE_USER}
```

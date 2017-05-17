
# FQT Database Core Manager

DBCManager (DBCM) is a core that help you to implement a database manager on your website.

It can be use with :
* [DBManagerBundle](https://github.com/hugo082/DBManagerBundle) : Implement web interface
* FQTDBRestManager (Coming soon)

Features include:
* Action control on entity
    * Default (List | Add | Edit | Remove)
    * Custom
* Access control
    * By roles
    * Custom

`v1.0` `15 MAI 17`

## Installation

### Step 1: Composer requirement

Add repositories to your `composer.json`

    "repositories" : [
        {
            "type" : "vcs",
            "url" : "https://github.com/hugo082/FQTDBCoreManagerBundle.git",
            "no-api": true
        }
    ]

Add requirement :

    "require": {
        "fqt/db-core-managerbundle": "1.0.*",
        //...
    },

Update your requirements with `composer update` command.

### Step 2: Bundle configuration

Enable the bundle in the kernel :

    <?php
    // app/AppKernel.php

    public function registerBundles()
    {
        $bundles = array(
            // ...
            new FQT\DBCoreManagerBundle\FQTDBCoreManagerBundle()
        );
    }

Set up your `config.yml` :

    fqtdb_core_manager:
        methods:
            service: 'my.custom.processor.db.action'

## About

DBManagerBundle is a FOUQUET initiative.
See also the [creator](https://github.com/hugo082).

## License

This bundle is under the MIT license. See the complete license [in the bundle](LICENSE)

## Documentation

### Add an entity

DBCM load your entities with your configuration file. You can specify an entity to follow by adding it in your config.yml

    fqtdb_core_manager:
        entities:
            DisplayName:
                fullName: YourBundle:RealName
                methods: [ list, add, edit, remove ]

You can configure different actions on each entity :

     DisplayName:
        fullName: YourBundle:RealName
        methods: [ action1, action2 ]
        formType: MyCustomFormType                          # Optional
        fullFormType: AnotherBundle\Form\MyCustomFormType   # Optional

By default, DBCM load your entity in `YourBundle\Entity\RealName`, name the form with `RealNameType` and load your form type in 
`YourBundle\Form\FormType` (so `YourBundle\Form\RealNameType`)
- `DisplayName` is used by DBM for display on template and in url, you can enter the same name of RealName.
- `methods` are actions that user can be execute on your entity. You have 4 default actions :
    - `list`
    - `add`
    - `edit`
    - `remove`

### Entity access

#### Role access

You can setup, for each entity, roles that are necessary to execute specific action or access to a specific information.<br>
For example, if you want that the entity is accessible only to admin users, you can specify the `access` config

    DisplayName:
        access: ROLE_ADMIN
        #...

You can also defined multi-roles :

    DisplayName:
        access: [ ROLE_ADMIN, ROLE_REDACTOR ]
        #...

If you want that users can list and so access to entity information but admins can execute actions on this entity, you
you can defined the parameter `access_details`. This parameter **must** defined roles for all actions :

    DisplayName:
        #...
        access_details:
            - { method: list, roles: [ ROLE_REDACTOR, ROLE_ADMIN ]}
            - { method: edit, roles: [ ROLE_ADMIN ]}
            - { method: remove, roles: [ ROLE_SUPER_ADMIN ]}

<span style="color:#FFC107">**WARNING** :</span> if you defined the access_details property, this parameter override access 
and so access is no longer taken into consideration.<br>


#### Custom constraints

You can implement an actionMethod to process a custom constraint. Your method will call for each entity and must return
a boolean (`true` to allow access and `false` to prevent).

    access_details:
        - { method: myCustomAction, check: myCustomCheckMethod }
        #...

This method is call on the service specified of your custom action (more information below).

When DBM list your entity, you can also choose your method repository. By default, DBManager use `findAll()` but you can 
override this easily :

    Flight:
        fullName: AppBundle:Flight
        listingMethod: myRepositoryMethod


#### Events

For add, edit and remove actions, events are called. You can listen them and execute a custom process :

    
    class ActionSubscriber implements EventSubscriberInterface {
    
        //...

        public static function getSubscribedEvents() {
            return array(
                DBManagerEvents::ACTION_REMOVE_BEFORE => 'beforeRemove',
                //...
            );
        }

        public function beforeRemove(ActionEvent $event) {
            $e = $event->getEntityObject();
            if ($e instanceof Flight) {
                if ($e->getId() == 13) {
                    $event->setExecuted(true); // DBM default action ignored
                    $event->setFlash('ERROR', 'You want remove VIP Flight');
                } else
                    $event->setFlash('SUCCESS', 'Your Flight have been removed');
            }
        }
    }

At the end of your process, if `executed` property of event are set to true, DBCM will ignore the default action. By default,
the `executed` property is set to false.<br>
Of course you must register your subscriber in your services.

### Actions

#### Default actions

By default, DBCM implement 4 actions (`list`, `add`, `edit`, `remove`). This methods can't be overrided and so you can't 
specify a custom check method. However, you can implement roles access.

#### Custom actions

All custom actions mus be defined in `methods.content` property of configuration file.

    methods:
        service: 'my.processor'
        content:
            methodID:
                method: 'multipleBillMargin'
                environment: 'object'            # object | global
                fullName: 'Method Name'          # Optionnal
                service: 'my.other.processor'    # Optionnal

For each action, you must define `method` property. This property define the method will be call to execute the action with
object in parameter (if environment is `object`) or null (if environment is `global`).
This method must return a `Data` object (more information below).

For each action, you must define the `environment` property. This property is used to define if action is applicable to an 
entity object (like `edit`) or global (like `add`).

By default, DBCM call method of your action in `methods.service` but you can override this service for each action with
property `service`.

By default, DBCM name your method `methodID` but you can override this name with `fullName` property.

#### Data object

Data object is an object of class `FQT\DBCoreManagerBundle\Core\Data`. You can init this class with an array parameter.
This object is a parameter in result of execution and is accessible in your template.

You can add customs parameters and defaults parameters. Defaults parameters are used by DBCM to execute actions.

For example, you can indicate if action have succeed, add flash message, and redirect result. You can also send custom parameter
like a form.

    return new Data(array(
        "success" => true,
        "redirect" => true,
        "form" => $form->createView(),
        "flash" => array(
            array("type" => 'success', "message" => 'Marge multiplié par ' . $data['value'])
        ))
    );
    
The redirect parameter can be a boolean or an array for more precision :

    "redirect" => array(
        "route_name" => "my.route.name",
        "data" => array()
    )
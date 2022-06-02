## Why

VxeController is designed to fast start working with [VxeTable](https://vxetable.cn/) table component.

Features:
- Data item query
- Data items listing
- Data items pagination
- Filtering

## Usage

### Server side
To start working make these steps:

- Create controller for model
  ```php
  // app\Http\Controller\DocumentController.php
  
  use App\Models\Document;
  use VxeController\Http\Controller\VxeController;
  
  class DocumentController extends VxeController {
    function model() {
      return Document::class;
    }
  }
  ```
  
- Register routes for controller:
  - manually
  - with simple `Route::vxeController` method for development purpose
    ```php
    // routes/web.php
    
    Route::vxeController(\App\Http\Controllers\DocumentController::class);
    ```
    Routes will be registered:
    - `/document` - [GET, POST] - list model items
    - `/document/update` - [POST] - update item
    - `/document/destroy` - [POST] - remove item

#### Methods query params

##### Listing

| Parameter | Example                                        | Description                                    |
|-----------|------------------------------------------------|------------------------------------------------|
| id        | 34                                             | get item with key (id) 34                      |
| page      | 1                                              | number of page (set this to enable pagination) |
| limit     | 20                                             | records per page                               |
| sort      | name                                           | field to order records                         |
| order     | asc                                            | sort order (default "asc")                     |
| filters   | {<br/>"datas": ["123"],<br/>"values": []<br/>} | filters data                                   |
  
### Client side

#### Using filters
  
```javascript
const filters = $vxeTable.getCheckedFilters().reduce((acc, cur) => {
  acc[cur.property] = {
    datas: cur.datas,
    values: cur.values
  };
  
  return acc;
}, {});
```

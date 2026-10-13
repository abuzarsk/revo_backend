# API Documentation

## Base URL
```
http://localhost/revo/public/
```

## Endpoints

### Home
- **GET /** - API information and available endpoints

### Users

#### Get All Users
- **GET /user**
- Response: List of all users

#### Get User by ID
- **GET /user/{id}**
- Parameters: `id` (integer)
- Response: User details

#### Create User
- **POST /user**
- Body: JSON
  ```json
  {
    "companySize": "",
    "groupLink": "",
    "mobile": "",
    "name": "",
    "role": "user",
    "slots": "",
    //"email": "john@example.com"
  }
  ```
- Response: Created user data

#### Update User
- **PUT /user/{id}** or **PATCH /user/{id}**
- Parameters: `id` (integer)
- Body: JSON
  ```json
  {
    "companySize": "",
    "groupLink": "",
    "mobile": "",
    "name": "",
    "role": "user",
    "slots": "",
  }
  ```
- Response: Updated user data

#### Delete User
- **DELETE /user/{id}**
- Parameters: `id` (integer)
- Response: Success message

## Response Format

### Success Response
```json
{
  "success": true,
  "message": "Success message",
  "data": {}
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error message",
  "errors": {}
}
```

## HTTP Status Codes
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `404` - Not Found
- `405` - Method Not Allowed
- `500` - Internal Server Error

## CORS
The API supports CORS and can be accessed from any origin.


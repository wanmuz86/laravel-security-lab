<form method="POST" action="/documents" enctype="multipart/form-data">
    @csrf
    <input type="file" name="document">
    <button type="submit">Upload</button>
</form>

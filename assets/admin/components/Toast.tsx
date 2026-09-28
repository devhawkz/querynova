export function Toast({ message }: { message: string }) {
  if (message === '') {
    return null;
  }
  return (
    <p className="qn-toast" role="status">
      {message}
    </p>
  );
}

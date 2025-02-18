for remote in origin local; do
  git push $remote --all
  git push $remote --tags
done
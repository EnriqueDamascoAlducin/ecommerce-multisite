import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function CustomerLogin() {
    return (
        <div className="mx-auto max-w-sm py-8">
            <Head title="Iniciar sesión" />
            <h1 className="mb-6 text-2xl font-semibold">Iniciar sesión</h1>

            <Form action="/cuenta/login" method="post" className="space-y-4">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Correo</Label>
                            <Input id="email" name="email" type="email" required autoFocus />
                            <InputError message={errors.email} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password">Contraseña</Label>
                            <Input id="password" name="password" type="password" required />
                            <InputError message={errors.password} />
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="remember" value="1" className="size-4 rounded" />
                            Recordarme
                        </label>
                        <Button className="w-full" disabled={processing}>Entrar</Button>
                    </>
                )}
            </Form>

            <div className="mt-4 flex justify-between text-sm text-neutral-500">
                <Link href="/cuenta/recuperar" className="hover:underline">¿Olvidaste tu contraseña?</Link>
                <Link href="/cuenta/registro" className="hover:underline">Crear cuenta</Link>
            </div>
        </div>
    );
}
